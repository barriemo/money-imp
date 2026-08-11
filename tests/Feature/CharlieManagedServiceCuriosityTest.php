<?php

namespace Tests\Feature;

use App\Domains\BusinessMemory\Actions\CreateBusinessMemory;
use App\Domains\CheerfulCharlie\Curiosity\CharlieAnswerIngestionService;
use App\Domains\CheerfulCharlie\Curiosity\CharlieQuestionService;
use App\Domains\ManagedServices\Actions\CreateManagedService;
use App\Domains\ManagedServices\Actions\LinkManagedServiceAsset;
use App\Models\Client;
use App\Models\ManagedServiceRequirement;
use App\Models\ManagedServiceTemplate;
use App\Models\SupplierAsset;
use App\Models\SupplierProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharlieManagedServiceCuriosityTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_managed_service_component_drives_next_question(): void
    {
        $client =
            Client::factory()->create();

        $supplier =
            SupplierProfile::create([
                'supplier_name' => 'Hosting Co',

                'supplier_key' => 'hosting-co',

                'category' => 'hosting',

                'recoverable' => true,

                'active' => true,
            ]);

        $template =
            ManagedServiceTemplate::create([
                'service_type' => 'managed_hosting',

                'name' => 'Managed Hosting',

                'active' => true,
            ]);

        foreach ([
            ['hosting_server', 'Hosting Server'],
            ['backup', 'Backup'],
            ['dns', 'DNS'],
        ] as [$type, $name]) {
            ManagedServiceRequirement::create([
                'managed_service_template_id' => $template->id,

                'component_type' => $type,

                'name' => $name,

                'required' => true,

                'minimum_count' => 1,

                'weight' => 1,
            ]);
        }

        $server =
            SupplierAsset::create([
                'supplier_profile_id' => $supplier->id,

                'asset_type' => 'hosting_server',

                'asset_key' => 'server-1',

                'name' => 'Server 1',

                'observed_cost' => 100,

                'active' => true,
            ]);

        $service = app(
            CreateManagedService::class
        )->execute(
            client: $client,
            type: 'managed_hosting',
            name: 'Managed Hosting',
            expectedMonthlyRevenue: 150
        );

        app(
            LinkManagedServiceAsset::class
        )->execute(
            service: $service,
            asset: $server,
            role: 'primary',
            verified: true
        );

        $memory = app(
            CreateBusinessMemory::class
        )->execute(
            $client
        );

        $question = app(
            CharlieQuestionService::class
        )->next(
            $memory
        );

        $this->assertSame(
            'backup_provider',
            $question['key']
        );

        $this->assertSame(
            'managed_service_gap',
            $question['source']
        );

        $this->assertSame(
            'backup',
            $question[
                'component_type'
            ]
        );

        $this->assertSame(
            100,
            $question['priority']
        );
    }

    public function test_managed_service_answer_creates_component_knowledge(): void
    {
        $client =
            Client::factory()->create();

        $template =
            ManagedServiceTemplate::create([
                'service_type' => 'managed_hosting',

                'name' => 'Managed Hosting',

                'active' => true,
            ]);

        ManagedServiceRequirement::create([
            'managed_service_template_id' => $template->id,

            'component_type' => 'backup',

            'name' => 'Backup',

            'required' => true,

            'minimum_count' => 1,

            'weight' => 1,
        ]);

        $service = app(
            CreateManagedService::class
        )->execute(
            client: $client,
            type: 'managed_hosting',
            name: 'Managed Hosting',
            expectedMonthlyRevenue: 185
        );

        $memory = app(
            CreateBusinessMemory::class
        )->execute(
            $client
        );

        $question = app(
            CharlieQuestionService::class
        )->next(
            $memory
        );

        app(
            CharlieAnswerIngestionService::class
        )->ingest(
            memory: $memory,
            question: $question,
            answer: 'Dave at XYZ IT looks after backups.'
        );

        $this->assertDatabaseHas(
            'managed_service_component_knowledge',
            [
                'managed_service_id' => $service->id,

                'component_type' => 'backup',

                'state' => 'externally_managed',

                'value' => 'Dave at XYZ IT looks after backups.',
            ]
        );
    }
}
