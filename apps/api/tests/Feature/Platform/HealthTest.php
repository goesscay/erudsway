<?php

it('reports health inside the data envelope', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonStructure(['data' => ['status', 'version', 'time']]);
});

it('renders unknown API routes with the error envelope', function () {
    $this->getJson('/api/v1/does-not-exist')
        ->assertNotFound()
        ->assertExactJson([
            'error' => [
                'code' => 'NOT_FOUND',
                'message' => 'The requested resource was not found.',
            ],
        ]);
});

it('allows credentialed requests from the frontend origin', function () {
    config(['cors.allowed_origins' => ['http://localhost:3000']]);

    $this->getJson('/api/v1/health', ['Origin' => 'http://localhost:3000'])
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:3000')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
});
