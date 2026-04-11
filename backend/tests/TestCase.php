<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * مساعد: ينفّذ طلب HTTP ثم يمسح cache الـ auth guard
     * لضمان أن الطلب التالي يُعيد تقييم المصادقة من قاعدة البيانات.
     */
    protected function flushAuthBetweenRequests(): void
    {
        $this->app['auth']->forgetGuards();
    }
}
