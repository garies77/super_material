<?php

namespace App\Http\Controllers;

use App\Events\FormSubmitted;
use App\Models\CustomForm;
use App\Support\Api\ClientHash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Formulir publik: halaman penuh /form/{slug} dan penerima kiriman untuk semua mode (halaman penuh & sematan).
 * Validasi mengikuti skema pertanyaan; error memakai error bag per formulir agar beberapa formulir di satu halaman tidak tercampur.
 */
class FormController extends Controller
{
    public function show(string $slug): View
    {
        $form = CustomForm::active()->where('slug', $slug)->firstOrFail();
        abort_unless($form->hasFullPage(), 404);

        return view('form', ['form' => $form]);
    }

    public function submit(Request $request, string $slug): RedirectResponse
    {
        $form = CustomForm::active()->where('slug', $slug)->firstOrFail();
        $back = fn () => redirect()->to($this->sameSiteUrl($request->input('source_url')) ?? url()->previous())->withFragment('form-'.$form->slug);

        // Honeypot terisi = bot. Tampilkan "berhasil" agar bot tidak belajar, tapi jangan simpan apa pun.
        if (filled($request->input(CustomForm::HONEYPOT))) {
            return $back()->with("form_success.{$form->id}", true);
        }

        $validator = Validator::make($request->all(), $form->validationRules(), $form->validationMessages(), $form->validationAttributes());
        if ($validator->fails()) {
            return $back()->withErrors($validator, 'form_'.$form->id)->withInput();
        }

        $validated = $validator->validated();
        $payload = collect($form->fields)->mapWithKeys(fn ($f) => [$f['key'] => $validated[$f['key']] ?? ($f['type'] === 'checkbox' ? [] : null)])->all();

        $submission = $form->submissions()->create([
            'payload' => $payload,
            ...$form->extractContact($payload),
            'source_url' => $this->sameSiteUrl($request->input('source_url')) ?? $this->sameSiteUrl($request->headers->get('referer')),
            'ip_hash' => ClientHash::forRequest($request),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
        ]);

        FormSubmitted::dispatch($submission);

        if ($redirect = $this->safeRedirect($form->redirect_url)) {
            return redirect()->to($redirect);
        }

        return $back()->with("form_success.{$form->id}", true);
    }

    /** Hanya URL di situs ini (mencegah open redirect lewat source_url / referer). */
    private function sameSiteUrl(mixed $url): ?string
    {
        if (! is_string($url) || $url === '') {
            return null;
        }

        return parse_url($url, PHP_URL_HOST) === parse_url(url('/'), PHP_URL_HOST) ? Str::limit($url, 2000, '') : null;
    }

    /** Pengalihan setelah kirim diatur admin: path situs ini atau URL https. */
    private function safeRedirect(?string $url): ?string
    {
        return filled($url) && preg_match('#^(/[^/\\\\]|https://)#i', $url) ? $url : null;
    }
}
