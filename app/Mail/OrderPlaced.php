<?php

namespace App\Mail;

use App\CentralLogics\BingoBitesOrderMailHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Mpdf\Mpdf;
use Illuminate\Support\Facades\View;

class OrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    protected int $order_id;

    public function __construct($order_id)
    {
        $this->order_id = (int) $order_id;
    }

    public function build()
    {
        $mailData = BingoBitesOrderMailHelper::build($this->order_id);
        $order = $mailData['order'];

        $pdfHtml = View::make('email-templates.bingo-bites-invoice', $mailData)->render();

        $mpdf = new Mpdf([
            'tempDir' => storage_path('tmp'),
            'default_font' => 'dejavusans',
            'mode' => 'utf-8',
        ]);

        $mpdf->autoScriptToLang = true;
        $mpdf->autoLangToFont = true;
        $mpdf->WriteHTML($pdfHtml);
        $pdfContent = $mpdf->Output('', 'S');

        return $this->subject('Order Confirmed - Bingo Bites #' . $order->id)
            ->view('email-templates.bingo-bites-order-placed', $mailData)
            ->attachData($pdfContent, 'Invoice_Order_' . $order->id . '.pdf', [
                'mime' => 'application/pdf',
            ]);
    }
}
