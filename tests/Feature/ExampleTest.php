<?php

test('the application returns a successful response', function () {
    $response = $this->get('/');

    // الصفحة الرئيسية محمية، لذلك يجب أن يُحوّل الضيف إلى تسجيل الدخول.
    $response->assertRedirect(route('login'));
});
