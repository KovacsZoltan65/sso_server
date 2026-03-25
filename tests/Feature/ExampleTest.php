<?php

it('redirects the root route to the dashboard', function () {
    $this->get('/')
        ->assertRedirect('/dashboard');
});
