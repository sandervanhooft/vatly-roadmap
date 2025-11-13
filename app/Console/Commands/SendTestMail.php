<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class SendTestMail extends Command
{
    protected $signature = 'test:mail {mailAddress}';
    protected $description = 'Send a test mail to the provided email address';

    public function handle()
    {
        $to = $this->argument('mailAddress');

        Mail::raw('Test mail body', function ($m) use ($to) {
            $m->to($to)->subject('Test mail');
        });

        $this->info("Sent to {$to}");
    }
}
