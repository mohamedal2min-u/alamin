<?php

namespace App\Http\Requests\Contact;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * قاعدة تحقق مشتركة: contact_id يجب أن يكون جهة من المزرعة الحالية.
 */
class ContactRule
{
    public static function exists(Request $request): Exists
    {
        return Rule::exists('contacts', 'id')->where('farm_id', (int) $request->attributes->get('farm_id'));
    }
}
