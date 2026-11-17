<?php

declare(strict_types=1);

test('a success flash message is shown with the success style', function () {
    $this->withSession(['success' => 'ذخیره شد'])
        ->get('/')
        ->assertOk()
        ->assertSee('flash--success', false)
        ->assertSee('ذخیره شد');
});

test('an error flash message uses the alert role', function () {
    $this->withSession(['error' => 'خطا'])
        ->get('/')
        ->assertOk()
        ->assertSee('role="alert"', false)
        ->assertSee('flash--error', false);
});

test('no flash element is rendered without a message', function () {
    $this->get('/')
        ->assertOk()
        ->assertDontSee('class="flash', false);
});
