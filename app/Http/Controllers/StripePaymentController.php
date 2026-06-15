<?php

namespace App\Http\Controllers;

use App\CentralLogics\Helpers;
use App\Model\BusinessSetting;
use App\Models\PaymentRequest;
use App\Traits\Processor;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Illuminate\Support\Facades\Validator;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;

class StripePaymentController extends Controller
{
    use Processor;

    private $config_values;
    private PaymentRequest $payment;

    public function __construct(PaymentRequest $payment)
    {
        $config = $this->payment_config('stripe', 'payment_config');
        if (!is_null($config) && $config->mode == 'live') {
            $this->config_values = json_decode($config->live_values);
        } elseif (!is_null($config) && $config->mode == 'test') {
            $this->config_values = json_decode($config->test_values);
        }
        $this->payment = $payment;
    }

    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_400, null, $this->error_processor($validator)), 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }
        $config = $this->config_values;

        return view('payment-gateway.stripe', compact('data', 'config'));
    }

    public function payment_process_3d(Request $request)
    {
        $data = $this->payment::where(['id' => $request['payment_id']])->where(['is_paid' => 0])->first();
        if (!isset($data)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }
        $payment_amount = $data['payment_amount'];

        Stripe::setApiKey($this->config_values->api_key);
        header('Content-Type: application/json');
        $currency_code = $data->currency_code;

        if ($data['additional_data'] != null) {
            $business = json_decode($data['additional_data']);
            $business_name = $business->business_name ?? "my_business";
            $business_logo = $business->business_logo ??  url('/');
        } else {
            $business_name = "my_business";
            $business_logo = url('/');
        }

        $checkout_session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency_code ?? 'usd',
                    'unit_amount' => round($payment_amount, 2) * 100,
                    'product_data' => [
                        'name' => $business_name,
                        'images' => [$business_logo],
                    ],
                ],
                'quantity' => 1,
            ]],
            'mode' => 'payment',
            'success_url' => url('/') . '/payment/stripe/success?session_id={CHECKOUT_SESSION_ID}&payment_id=' . $data->id,
            'cancel_url' => url()->previous(),
        ]);

        return response()->json(['id' => $checkout_session->id]);
    }

    public function success(Request $request)
    {
        Stripe::setApiKey($this->config_values->api_key);
        $session = Session::retrieve($request->get('session_id'));

        if ($session->payment_status == 'paid' && $session->status == 'complete') {

            $this->payment::where(['id' => $request['payment_id']])->update([
                'payment_method' => 'stripe',
                'is_paid' => 1,
                'transaction_id' => $session->payment_intent,
            ]);

            $data = $this->payment::where(['id' => $request['payment_id']])->first();

            if (isset($data) && function_exists($data->success_hook)) {
                call_user_func($data->success_hook, $data);
            }

            return $this->payment_response($data,'success');
        }
        $payment_data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (isset($payment_data) && function_exists($payment_data->failure_hook)) {
            call_user_func($payment_data->failure_hook, $payment_data);
        }
        return $this->payment_response($payment_data,'fail');
    }

    public function createMobilePaymentIntent(PaymentRequest $data): JsonResponse
    {
        if (!isset($this->config_values) || empty($this->config_values->api_key)) {
            return response()->json(['errors' => [['message' => 'Stripe is not configured']]], 403);
        }

        $unpaidPayment = $this->payment::where(['id' => $data->id])->where(['is_paid' => 0])->first();
        if (!isset($unpaidPayment)) {
            return response()->json($this->response_formatter(GATEWAYS_DEFAULT_204), 200);
        }

        Stripe::setApiKey($this->config_values->api_key);

        $paymentIntent = PaymentIntent::create([
            'amount' => (int) round($unpaidPayment->payment_amount * 100),
            'currency' => strtolower($unpaidPayment->currency_code ?? 'usd'),
            'automatic_payment_methods' => ['enabled' => true],
            'metadata' => ['payment_id' => $unpaidPayment->id],
        ]);

        $this->payment::where(['id' => $unpaidPayment->id])->update([
            'transaction_id' => $paymentIntent->id,
        ]);

        return response()->json([
            'stripe_native' => true,
            'payment_id' => $unpaidPayment->id,
            'publishable_key' => $this->config_values->published_key,
            'client_secret' => $paymentIntent->client_secret,
            'amount' => (int) round($unpaidPayment->payment_amount * 100),
            'currency' => strtolower($unpaidPayment->currency_code ?? 'usd'),
        ], 200);
    }

    public function confirmPaymentIntent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_id' => 'required|uuid',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $this->error_processor($validator)], 400);
        }

        $data = $this->payment::where(['id' => $request['payment_id']])->first();
        if (!isset($data)) {
            return response()->json(['errors' => [['message' => 'Payment not found']]], 404);
        }

        if ($data->is_paid == 1) {
            return response()->json([
                'status' => 'success',
                'payment_method' => $data->payment_method,
                'transaction_reference' => $data->transaction_id,
                'attribute' => $data->attribute,
                'attribute_id' => $data->attribute_id,
            ], 200);
        }

        if (empty($data->transaction_id)) {
            return response()->json(['errors' => [['message' => 'Payment intent not found']]], 400);
        }

        Stripe::setApiKey($this->config_values->api_key);
        $paymentIntent = PaymentIntent::retrieve($data->transaction_id);

        if ($paymentIntent->status !== 'succeeded') {
            if (isset($data->failure_hook) && function_exists($data->failure_hook)) {
                call_user_func($data->failure_hook, $data);
            }

            return response()->json(['status' => 'fail', 'message' => 'Payment not completed'], 400);
        }

        if (($paymentIntent->metadata['payment_id'] ?? '') !== $data->id) {
            return response()->json(['errors' => [['message' => 'Payment verification failed']]], 400);
        }

        $expectedAmount = (int) round($data->payment_amount * 100);
        if ($paymentIntent->amount !== $expectedAmount) {
            return response()->json(['errors' => [['message' => 'Payment amount mismatch']]], 400);
        }

        if (strtolower($paymentIntent->currency) !== strtolower($data->currency_code)) {
            return response()->json(['errors' => [['message' => 'Payment currency mismatch']]], 400);
        }

        $this->payment::where(['id' => $data->id])->update([
            'payment_method' => 'stripe',
            'is_paid' => 1,
            'transaction_id' => $paymentIntent->id,
        ]);

        $data = $this->payment::where(['id' => $data->id])->first();

        if (isset($data) && function_exists($data->success_hook)) {
            call_user_func($data->success_hook, $data);
        }

        return response()->json([
            'status' => 'success',
            'payment_method' => $data->payment_method,
            'transaction_reference' => $data->transaction_id,
            'attribute' => $data->attribute,
            'attribute_id' => $data->attribute_id,
        ], 200);
    }
}



