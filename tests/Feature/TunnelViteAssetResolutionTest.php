<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TunnelViteAssetResolutionTest extends TestCase
{
    use RefreshDatabase;

    private string $hotPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withVite();
        $this->hotPath = public_path('hot');
    }

    protected function tearDown(): void
    {
        if (File::exists($this->hotPath)) {
            File::delete($this->hotPath);
        }
        parent::tearDown();
    }

    public function test_it_uses_compiled_assets_instead_of_hot_file_when_accessed_via_tunnel_domain(): void
    {
        File::put($this->hotPath, 'https://hospital-formulary-drug-proposal-system.test:5173');

        $response = $this->get('https://assume-political-lcd-frequencies.trycloudflare.com/recap');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringNotContainsString('https://hospital-formulary-drug-proposal-system.test:5173', $content);
        $this->assertStringContainsString('/build/assets/', $content);
    }

    public function test_it_preserves_hot_file_when_accessed_from_local_development_domain(): void
    {
        File::put($this->hotPath, 'https://hospital-formulary-drug-proposal-system.test:5173');

        $response = $this->get('https://hospital-formulary-drug-proposal-system.test/recap');

        $response->assertOk();
        $content = $response->getContent();

        $this->assertStringContainsString('https://hospital-formulary-drug-proposal-system.test:5173', $content);
    }
}
