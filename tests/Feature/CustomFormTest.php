<?php

use App\Events\FormSubmitted;
use App\Filament\Resources\CustomForms\Pages\CreateCustomForm;
use App\Filament\Resources\FormSubmissions\Pages\ListFormSubmissions;
use App\Models\CustomForm;
use App\Models\FormSubmission;
use App\Models\HomeSection;
use App\Models\Page;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\FormSubmissionConfirmation;
use App\Notifications\FormSubmissionReceived;
use App\Support\SiteLayout;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(DatabaseSeeder::class));

function validAnswers(array $overrides = []): array
{
    return array_merge([
        'nama_lengkap' => 'Budi Santoso',
        'email' => 'budi@contoh.id',
        'nomor_whatsapp' => '0812-3456-7890',
        'kebutuhan' => 'Semen',
        'material_diminati' => ['Cat', 'Atap'],
        'pesan' => 'Butuh 200 sak.',
    ], $overrides);
}

it('shows a full-page form with every question and hides it when the form is embed-only or inactive', function () {
    $form = CustomForm::factory()->create(['slug' => 'konsultasi-proyek']);

    $this->get('/form/konsultasi-proyek')->assertOk()
        ->assertSee('Nama lengkap')->assertSee('Nomor WhatsApp')->assertSee('Material diminati')
        ->assertSee('name="'.CustomForm::HONEYPOT.'"', false)
        ->assertSee('noindex', false);

    $form->update(['display_mode' => 'embed']);
    $this->get('/form/konsultasi-proyek')->assertNotFound();

    $form->update(['display_mode' => 'both', 'is_active' => false]);
    $this->get('/form/konsultasi-proyek')->assertNotFound();
});

it('stores a valid submission with contact columns and source page, then fires FormSubmitted', function () {
    $form = CustomForm::factory()->create();
    Event::fake([FormSubmitted::class]);

    $this->from(url('/produk'))
        ->post("/form/{$form->slug}", validAnswers(['source_url' => url('/produk/semen-tiga-roda-50-kg')]))
        ->assertRedirect(url('/produk/semen-tiga-roda-50-kg').'#form-'.$form->slug)
        ->assertSessionHas("form_success.{$form->id}");

    $submission = FormSubmission::sole();
    expect($submission->only(['name', 'email', 'phone', 'status', 'source_url']))->toBe([
        'name' => 'Budi Santoso',
        'email' => 'budi@contoh.id',
        'phone' => '0812-3456-7890',
        'status' => 'new',
        'source_url' => url('/produk/semen-tiga-roda-50-kg'),
    ])
        ->and($submission->payload['material_diminati'])->toBe(['Cat', 'Atap'])
        ->and($submission->ip_hash)->not->toBeNull()->not->toBe('127.0.0.1');
    Event::assertDispatched(FormSubmitted::class, fn ($e) => $e->submission->is($submission));
});

it('shows the success message after submitting', function () {
    $form = CustomForm::factory()->create(['success_message' => 'Tim kami segera menghubungi Anda.']);

    $this->followingRedirects()->from($form->url())
        ->post("/form/{$form->slug}", validAnswers(['source_url' => $form->url()]))
        ->assertOk()->assertSee('Tim kami segera menghubungi Anda.');
});

it('validates answers against the form schema, in an error bag of its own', function () {
    $form = CustomForm::factory()->create();
    $bag = 'form_'.$form->id;

    $this->post("/form/{$form->slug}", [])->assertSessionHasErrorsIn($bag, ['nama_lengkap' => 'Kolom nama lengkap wajib diisi.', 'email', 'kebutuhan']);
    $this->post("/form/{$form->slug}", validAnswers(['email' => 'bukan-email']))->assertSessionHasErrorsIn($bag, ['email' => 'Masukkan alamat email yang valid.']);
    $this->post("/form/{$form->slug}", validAnswers(['kebutuhan' => 'Pilihan Palsu']))->assertSessionHasErrorsIn($bag, ['kebutuhan']);
    $this->post("/form/{$form->slug}", validAnswers(['material_diminati' => ['Cat', 'Narkoba']]))->assertSessionHasErrorsIn($bag, ['material_diminati.1']);
    $this->post("/form/{$form->slug}", validAnswers(['nomor_whatsapp' => 'telepon saya']))->assertSessionHasErrorsIn($bag, ['nomor_whatsapp']);

    expect(FormSubmission::count())->toBe(0);
});

it('silently drops submissions that fill the honeypot', function () {
    $form = CustomForm::factory()->create();
    Event::fake([FormSubmitted::class]);

    $this->post("/form/{$form->slug}", validAnswers([CustomForm::HONEYPOT => 'http://spam.example']))
        ->assertRedirect()->assertSessionHas("form_success.{$form->id}");

    expect(FormSubmission::count())->toBe(0);
    Event::assertNotDispatched(FormSubmitted::class);
});

it('rate limits repeated submissions from one address', function () {
    $form = CustomForm::factory()->create();

    for ($i = 0; $i < 5; $i++) {
        $this->post("/form/{$form->slug}", validAnswers())->assertRedirect();
    }

    $this->post("/form/{$form->slug}", validAnswers())->assertTooManyRequests();
    expect(FormSubmission::count())->toBe(5);
});

it('ignores off-site source URLs and only follows safe redirect targets', function () {
    $form = CustomForm::factory()->create();

    $response = $this->post("/form/{$form->slug}", validAnswers(['source_url' => 'https://evil.example/phish']))->assertRedirect();

    expect($response->headers->get('Location'))->not->toContain('evil.example')
        ->and(FormSubmission::sole()->source_url)->toBeNull();

    $form->update(['redirect_url' => '/terima-kasih']);
    $this->post("/form/{$form->slug}", validAnswers())->assertRedirect('/terima-kasih');

    $form->update(['redirect_url' => '//evil.example']);
    $this->post("/form/{$form->slug}", validAnswers())->assertRedirectContains('#form-'.$form->slug);
});

it('notifies the admin and, when enabled, sends a confirmation to the submitter', function () {
    $form = CustomForm::factory()->create(['notify_email' => 'sales@supermaterial.test', 'send_confirmation' => true]);
    Notification::fake();

    $this->post("/form/{$form->slug}", validAnswers())->assertRedirect();

    Notification::assertSentTo(new AnonymousNotifiable, FormSubmissionReceived::class,
        fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'sales@supermaterial.test');
    Notification::assertSentTo(new AnonymousNotifiable, FormSubmissionConfirmation::class,
        fn ($n, $channels, $notifiable) => $notifiable->routes['mail'] === 'budi@contoh.id');
});

it('sends nothing when no notification email or confirmation is configured', function () {
    $form = CustomForm::factory()->create();
    Notification::fake();

    $this->post("/form/{$form->slug}", validAnswers())->assertRedirect();

    Notification::assertNothingSent();
});

it('embeds forms on the homepage, in static pages, and in the footer, but never full-page-only forms', function () {
    $form = CustomForm::factory()->create(['title' => 'Daftar Newsletter Uji']);
    HomeSection::create(['key' => 'form_test', 'type' => 'form', 'title' => 'Kabar Promo', 'sort_order' => 9, 'settings' => ['form_id' => $form->id]]);
    $page = Page::first();
    $page->forceFill(['blocks' => [['type' => 'form', 'data' => ['form_id' => $form->id, 'show_title' => true]]], 'is_published' => true])->save();
    Setting::put(SiteLayout::FOOTER_KEY, json_encode(['columns' => [['title' => 'Berlangganan', 'type' => 'form', 'form_id' => $form->id, 'is_active' => true]]] + SiteLayout::footerDefaults()));

    $home = $this->get('/')->assertOk()->getContent();
    expect($home)->toContain('Kabar Promo')->toContain('action="'.route('form.submit', $form->slug).'"')
        ->and(substr_count($home, 'action="'.route('form.submit', $form->slug).'"'))->toBe(2); // beranda + footer
    $this->get($page->url())->assertOk()->assertSee('Daftar Newsletter Uji');

    $form->update(['display_mode' => 'full']);
    $this->get('/')->assertDontSee('action="'.route('form.submit', $form->slug).'"', false);
});

it('lets editors build a form with unique question keys, and keeps authors out', function () {
    $this->actingAs(User::factory()->create(['role' => 'editor']));

    Livewire::test(CreateCustomForm::class)
        ->fillForm([
            'title' => 'Survei Pelanggan', 'slug' => 'survei-pelanggan', 'name' => 'Survei', 'display_mode' => 'both', 'is_active' => true,
            'fields' => [
                ['label' => 'Email', 'type' => 'email', 'required' => true],
                ['label' => 'Email', 'type' => 'email'],
                ['label' => 'Website URL', 'type' => 'text'],
                ['label' => 'Ukuran', 'type' => 'radio', 'options' => ['Kecil', 'Besar']],
            ],
        ])
        ->call('create')->assertHasNoFormErrors();

    expect(collect(CustomForm::where('slug', 'survei-pelanggan')->sole()->fields)->pluck('key')->all())
        ->toBe(['email', 'email_2', 'website_url_1', 'ukuran']);

    $this->actingAs(User::factory()->create(['role' => 'author']));
    $this->get('/admin/formulir')->assertForbidden();
    $this->get('/admin/jawaban-formulir')->assertForbidden();
});

it('exports the filtered submissions as CSV with contacts first and spreadsheet formulas neutralised', function () {
    $this->actingAs(User::where('email', 'admin@supermaterial.test')->first());
    $form = CustomForm::factory()->create();
    $other = CustomForm::factory()->create();
    FormSubmission::factory()->for($form, 'form')->create(['name' => 'Ani', 'email' => 'ani@contoh.id', 'payload' => ['nama_lengkap' => 'Ani', 'email' => 'ani@contoh.id', 'pesan' => '=HYPERLINK("http://x")']]);
    FormSubmission::factory()->for($other, 'form')->create(['email' => 'lain@contoh.id']);

    $response = Livewire::test(ListFormSubmissions::class)->filterTable('custom_form_id', $form->id)->instance()->exportCsv();
    ob_start();
    $response->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toStartWith("\xEF\xBB\xBF\"Tanggal (WIB)\",Formulir,Nama,Email,Telepon")
        ->toContain('ani@contoh.id')->not->toContain('lain@contoh.id')
        ->toContain("'=HYPERLINK");
});
