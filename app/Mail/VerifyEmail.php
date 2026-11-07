<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class VerifyEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Defaults on purpose: the build refuses a class whose constructor has a
     * required parameter (it reads that as "the container must resolve this",
     * which Laravel cannot do either), and a Mailable is compiled with the
     * controller that sends it. Both values are always passed here.
     */
    public function __construct(
        public string $name = '',
        public string $verificationUrl = '',
    ) {}

    /**
     * The body is INLINE html, not a Blade view, on purpose.
     *
     * The compiled register route never renders `emails.verify-email` itself, so
     * the build emits that handler with an empty view map; the mailable's render
     * then throws `view not compiled` at request time and the POST answers 500.
     * `Illuminate\Mail\Mailable::html()` (vendor:944) carries the body in the
     * `$html` property, which Laravel's `render()` prefers over the view
     * (vendor:331) and which the kernel's `__lwMail()` reads as `__html` — no
     * view lookup, on PHP or at the edge.
     *
     * The markup lives here and nowhere else: a Blade twin would be a second
     * source that rots the moment one side is edited.
     */
    public function build(): static
    {
        return $this
            ->subject('Verifikasi email nearFind')
            ->html($this->bodyHtml());
    }

    private function bodyHtml(): string
    {
        $name = e($this->name);
        $url = e($this->verificationUrl);

        return <<<HTML
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Verifikasi email nearFind</title>
        </head>
        <body style="margin:0;padding:0;background:#0B0E12;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#0B0E12;padding:32px 16px;">
                <tr>
                    <td align="center">
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#141A21;border-radius:24px;padding:32px;">
                            <tr>
                                <td>
                                    <p style="margin:0 0 6px;font-size:12px;letter-spacing:0.15em;text-transform:uppercase;color:#9FB3C8;">nearFind</p>
                                    <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#F3F6FA;">Halo {$name}, satu langkah lagi</h1>
                                    <p style="margin:0 0 24px;font-size:14px;line-height:1.65;color:#C6D2DE;">
                                        Akunmu belum aktif. Buka tautan di bawah ini untuk memverifikasi email, lalu masuk ke nearFind dan mulai mendukung kebutuhan di sekitarmu.
                                    </p>
                                    <p style="margin:0 0 24px;">
                                        <a href="{$url}" style="display:inline-block;background:#005C55;color:#FFFFFF;font-size:14px;font-weight:600;text-decoration:none;padding:13px 22px;border-radius:12px;">Verifikasi email</a>
                                    </p>
                                    <p style="margin:0 0 8px;font-size:12px;line-height:1.6;color:#9FB3C8;">
                                        Kalau tombolnya tidak bisa dibuka, salin tautan ini ke peramban:
                                    </p>
                                    <p style="margin:0 0 24px;font-size:12px;line-height:1.6;color:#C6D2DE;word-break:break-all;">
                                        {$url}
                                    </p>
                                    <p style="margin:0;font-size:12px;line-height:1.6;color:#8A9AA9;">
                                        Kalau kamu tidak merasa mendaftar di nearFind, abaikan saja email ini — akunnya tidak akan aktif.
                                    </p>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </body>
        </html>
        HTML;
    }
}
