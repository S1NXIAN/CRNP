<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The admin's **Mark refunded** action (PLAN §4 *Admin* — *Reports*). Access is
 * settled by the `staff.role:admin` guard on the route; the request carries no
 * body — the order id is the route parameter.
 */
final class MarkRefundedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
