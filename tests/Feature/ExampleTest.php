<?php

test('the home page redirects to registration', function () {
    $this->get('/')->assertRedirect('/register');
});
