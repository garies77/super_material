<?php

namespace Database\Factories;

use App\Models\CustomForm;
use App\Models\FormSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FormSubmission>
 */
class FormSubmissionFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->name();
        $email = fake()->unique()->safeEmail();

        return [
            'custom_form_id' => CustomForm::factory(),
            'payload' => ['nama_lengkap' => $name, 'email' => $email, 'kebutuhan' => 'Semen'],
            'name' => $name,
            'email' => $email,
            'status' => 'new',
            'source_url' => 'http://localhost/',
        ];
    }
}
