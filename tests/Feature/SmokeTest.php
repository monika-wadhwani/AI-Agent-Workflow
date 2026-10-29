<?php

it('loads the home page', function (){
    $this->get('/')->assertStatus(200);
});

it('returns 404 for a page that does not exist', function (){
    $this->get('this-page-does-not-exist')->assertStatus(404);
});