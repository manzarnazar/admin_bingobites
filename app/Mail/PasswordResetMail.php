<?php

namespace App\Mail;

use App\CentralLogics\Helpers;
use App\Model\BusinessSetting;
use App\Models\EmailTemplate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    protected $token;
    protected $name;
    protected $language_code;

    public function __construct($token, $name, $language_code)
    {
        $this->token = $token;
        $this->name = $name;
        $this->language_code = $language_code;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $code = $this->token;
       // return $this->view('email-templates.customer-password-reset', ['token' => $token]);

        $data = EmailTemplate::with('translations')->where('type', 'user')->where('email_type', 'forget_password')->first();
        $local = $this->language_code ?? 'en';

        $content = [
            'title' => $data?->title ?? translate('Customer_Password_Reset_mail'),
            'body' => $data?->body ?? '<p>Please use the verification code below to reset your password.</p>',
            'footer_text' => $data?->footer_text ?? translate('Please_contact_us_for_any_queries,_we’re_always_happy_to_help.'),
            'copyright_text' => $data?->copyright_text ?? translate('Copyright_2023_eFood._All_right_reserved'),
        ];

        if ($local != 'en' && isset($data->translations)) {
            foreach ($data->translations as $translation) {
                if ($local == $translation->locale) {
                    $content[$translation->key] = $translation->value;
                }
            }
        }

        $template = $data?->email_template ?? 4;
        $url = '';
        $customer_name = $this->name;
        $company_name = BusinessSetting::where('key', 'restaurant_name')->first()->value;
        $title = Helpers::text_variable_data_format( value:$content['title']??'',user_name:$customer_name??'');
        $body = Helpers::text_variable_data_format( value:$content['body']??'',user_name:$customer_name??'');
        $footer_text = Helpers::text_variable_data_format( value:$content['footer_text']??'',user_name:$customer_name??'');
        $copyright_text = Helpers::text_variable_data_format( value:$content['copyright_text']??'',user_name:$customer_name??'');
        return $this->subject(translate('Customer_Password_Reset_mail'))->view('email-templates.new-email-format-'.$template, ['company_name'=>$company_name,'data'=>$data,'title'=>$title,'body'=>$body,'footer_text'=>$footer_text,'copyright_text'=>$copyright_text,'url'=>$url, 'code'=>$code]);

    }
}
