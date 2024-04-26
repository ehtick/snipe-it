<?php

namespace Tests\Feature\Api\Assets;

use App\Models\Asset;
use App\Models\Company;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\TestCase;
use Carbon;
class AssetIndexTest extends TestCase
{
    public function testAssetApiIndexReturnsExpectedAssets()
    {
        Asset::factory()->count(3)->create();

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.assets.index', [
                    'sort' => 'name',
                    'order' => 'asc',
                    'offset' => '0',
                    'limit' => '20',
                ]))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ])
            ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
    }

    public function testAssetApiIndexReturnsDisplayUpcomingAuditsDueToday()
    {
        Asset::factory()->count(3)->create(['next_audit_date' => Carbon::now()->format('Y-m-d')]);

        $this->assertTrue(Asset::count() === 3);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.asset.to-audit', ['status' => 'due']))
                ->assertOk()
                ->assertJsonStructure([
                    'total',
                    'rows',
                ])
            ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());

    }

    public function testAssetApiIndexReturnsOverdueForAudit()
    {
        Asset::factory()->count(3)->create(['next_audit_date' => Carbon::now()->subDays(1)->format('Y-m-d')]);

        $this->assertTrue(Asset::count() === 3);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.asset.to-audit', ['status' => 'overdue']))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ])
            ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
    }


    public function testAssetApiIndexReturnsDueForExpectedCheckinToday()
    {
        Asset::factory()->count(3)->create(['expected_checkin' => Carbon::now()->format('Y-m-d')]);

        $this->assertTrue(Asset::count() === 3);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(
                route('api.asset.to-checkin', ['status' => 'due'])
            )
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
        ])
        ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
    }

    public function testAssetApiIndexReturnsOverdueForExpectedCheckin()
    {
        Asset::factory()->count(3)->create(['expected_checkin' => Carbon::now()->subDays(1)->format('Y-m-d')]);

        $this->assertTrue(Asset::count() === 3);

        $this->actingAsForApi(User::factory()->superuser()->create())
            ->getJson(route('api.asset.to-checkin', ['status' => 'overdue']))
            ->assertOk()
            ->assertJsonStructure([
                'total',
                'rows',
            ])
            ->assertJson(fn(AssertableJson $json) => $json->has('rows', 3)->etc());
    }

    public function testAssetApiIndexAdheresToCompanyScoping()
    {
        [$companyA, $companyB] = Company::factory()->count(2)->create();

        $assetA = Asset::factory()->for($companyA)->create();
        $assetB = Asset::factory()->for($companyB)->create();

        $superUser = $companyA->users()->save(User::factory()->superuser()->make());
        $userInCompanyA = $companyA->users()->save(User::factory()->viewAssets()->make());
        $userInCompanyB = $companyB->users()->save(User::factory()->viewAssets()->make());

        $this->settings->disableMultipleFullCompanySupport();

        $this->actingAsForApi($superUser)
            ->getJson(route('api.assets.index'))
            ->assertResponseContainsInRows($assetA, 'asset_tag')
            ->assertResponseContainsInRows($assetB, 'asset_tag');

        $this->actingAsForApi($userInCompanyA)
            ->getJson(route('api.assets.index'))
            ->assertResponseContainsInRows($assetA, 'asset_tag')
            ->assertResponseContainsInRows($assetB, 'asset_tag');

        $this->actingAsForApi($userInCompanyB)
            ->getJson(route('api.assets.index'))
            ->assertResponseContainsInRows($assetA, 'asset_tag')
            ->assertResponseContainsInRows($assetB, 'asset_tag');

        $this->settings->enableMultipleFullCompanySupport();

        $this->actingAsForApi($superUser)
            ->getJson(route('api.assets.index'))
            ->assertResponseContainsInRows($assetA, 'asset_tag')
            ->assertResponseContainsInRows($assetB, 'asset_tag');

        $this->actingAsForApi($userInCompanyA)
            ->getJson(route('api.assets.index'))
            ->assertResponseContainsInRows($assetA, 'asset_tag')
            ->assertResponseDoesNotContainInRows($assetB, 'asset_tag');

        $this->actingAsForApi($userInCompanyB)
            ->getJson(route('api.assets.index'))
            ->assertResponseDoesNotContainInRows($assetA, 'asset_tag')
            ->assertResponseContainsInRows($assetB, 'asset_tag');
    }
}
