<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        if (!DB::table('email_templates')->where('type', 'user')->where('email_type', 'registration_otp')->exists()) {
            DB::table('email_templates')->insert([
                'title' => 'Registration OTP',
                'body' => '<p>Please use the verification code below to complete your registration.</p>',
                'footer_text' => 'Please contact us for any queries, we are always happy to help.',
                'copyright_text' => 'Copyright Bingo Bites. All rights reserved.',
                'type' => 'user',
                'email_type' => 'registration_otp',
                'email_template' => '4',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (!DB::table('email_templates')->where('type', 'user')->where('email_type', 'forget_password')->exists()) {
            DB::table('email_templates')->insert([
                'title' => 'Password Reset OTP',
                'body' => '<p>Please use the verification code below to reset your password.</p>',
                'footer_text' => 'Please contact us for any queries, we are always happy to help.',
                'copyright_text' => 'Copyright Bingo Bites. All rights reserved.',
                'type' => 'user',
                'email_type' => 'forget_password',
                'email_template' => '4',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $mailSettings = [
            'registration_otp_mail_status_user' => '1',
            'forget_password_mail_status_user' => '1',
        ];

        foreach ($mailSettings as $key => $value) {
            if (!DB::table('business_settings')->where('key', $key)->exists()) {
                DB::table('business_settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        Cache::forget('cache_business_settings_table');
    }

    public function down(): void
    {
        DB::table('email_templates')
            ->where('type', 'user')
            ->whereIn('email_type', ['registration_otp', 'forget_password'])
            ->delete();

        DB::table('business_settings')
            ->whereIn('key', ['registration_otp_mail_status_user', 'forget_password_mail_status_user'])
            ->delete();

        Cache::forget('cache_business_settings_table');
    }
};
