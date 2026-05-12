<?php

namespace Tests\Feature;

use App\Models\AppNotification;
use App\Models\FileStorageAccess;
use App\Models\SharedFile;
use App\Models\FileSend;
use App\Models\StorageFolder;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Support\PlanPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PersonalStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_save_file_to_personal_storage_when_plan_allows_it(): void
    {
        Storage::fake();

        $user = User::factory()->create([
            'username' => 'storage-user',
            'email' => 'storage@example.com',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Premium Storage',
            'slug' => 'premium-storage',
            'description' => 'Storage enabled plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 10,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 5,
            'expire_options' => ['default', '24', 'custom', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => true,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($user, $plan);

        $this->actingAs($user)
            ->get(route('files.create', ['destination' => 'storage']))
            ->assertOk()
            ->assertSee(__('ui.send.destination_storage'))
            ->assertSee(__('ui.send.save_to_storage'));

        $this->actingAs($user)
            ->post(route('files.store'), [
                'destination' => 'storage',
                'message' => 'Keep this for later',
                'file' => UploadedFile::fake()->create('personal.pdf', 1024, 'application/pdf'),
            ])
            ->assertRedirect(route('storage.index'));

        $file = SharedFile::query()->where('owner_id', $user->id)->firstOrFail();

        $this->assertTrue($file->is_personal_storage);
        $this->assertNull($file->expires_at);
        $this->assertSame(SharedFile::STATUS_ACTIVE, $file->status);
        $this->assertDatabaseHas('file_sends', [
            'file_id' => $file->id,
            'sender_id' => $user->id,
            'receiver_id' => $user->id,
        ]);
        $this->assertDatabaseHas('app_notifications', [
            'user_id' => $user->id,
            'type' => 'personal_storage_received',
        ]);

        $this->actingAs($user)
            ->get(route('storage.index'))
            ->assertOk()
            ->assertSee('personal.pdf');

        $this->actingAs($user)
            ->get(route('inbox'))
            ->assertOk()
            ->assertSee('personal.pdf');

        $this->actingAs($user)
            ->get(route('conversations.storage'))
            ->assertOk()
            ->assertSee('personal.pdf');
    }

    public function test_personal_storage_enforces_plan_quota(): void
    {
        Storage::fake();

        $user = User::factory()->create([
            'username' => 'quota-user',
            'email' => 'quota@example.com',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Starter Storage',
            'slug' => 'starter-storage',
            'description' => 'Small storage quota',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 20,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 1,
            'expire_options' => ['default', '24'],
            'allow_public_links' => false,
            'allow_password_protection' => false,
            'allow_custom_expiry' => false,
            'allow_never_expire' => false,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($user, $plan);

        SharedFile::query()->create([
            'owner_id' => $user->id,
            'is_personal_storage' => true,
            'original_name' => 'existing.pdf',
            'stored_name' => 'existing.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 900 * 1024,
            'storage_path' => 'files/2026/05/existing.pdf',
            'checksum' => 'abc',
            'expires_at' => null,
            'status' => SharedFile::STATUS_ACTIVE,
            'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
        ]);

        $this->actingAs($user)
            ->post(route('files.store'), [
                'destination' => 'storage',
                'file' => UploadedFile::fake()->create('too-much.pdf', 300, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_personal_storage_supports_search_filters_and_grid_view(): void
    {
        Storage::fake();

        $owner = User::factory()->create([
            'username' => 'storage-owner',
            'email' => 'owner@example.com',
        ]);

        $sender = User::factory()->create([
            'username' => 'ali-sender',
            'email' => 'ali@example.com',
            'full_name' => 'Ali Sender',
        ]);
        $recipient = User::factory()->create([
            'username' => 'reza-recipient',
            'email' => 'reza@example.com',
            'full_name' => 'Reza Recipient',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Search Storage',
            'slug' => 'search-storage',
            'description' => 'Storage search plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 30,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($owner, $plan);

        Storage::put('files/2026/05/note.txt', 'payment note from ali');
        Storage::put('files/2026/05/photo.jpg', 'fake image');

        $noteFile = SharedFile::query()->create([
            'owner_id' => $owner->id,
            'is_personal_storage' => true,
            'original_name' => 'note-1.txt',
            'stored_name' => 'note-1.txt',
            'mime_type' => 'text/plain',
            'extension' => 'txt',
            'size' => 22,
            'storage_path' => 'files/2026/05/note.txt',
            'checksum' => 'note',
            'expires_at' => null,
            'status' => SharedFile::STATUS_ACTIVE,
            'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
        ]);

        $imageFile = SharedFile::query()->create([
            'owner_id' => $owner->id,
            'is_personal_storage' => true,
            'original_name' => 'image-1.jpg',
            'stored_name' => 'image-1.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 1200,
            'storage_path' => 'files/2026/05/photo.jpg',
            'checksum' => 'image',
            'expires_at' => null,
            'status' => SharedFile::STATUS_ACTIVE,
            'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
        ]);

        FileSend::query()->create([
            'file_id' => $noteFile->id,
            'sender_id' => $sender->id,
            'receiver_id' => $recipient->id,
            'message' => 'payment record',
        ]);

        $this->actingAs($owner)
            ->get(route('storage.index', [
                'q' => 'payment',
                'type' => 'note',
                'sender' => $sender->username,
                'recipient' => $recipient->username,
                'view' => 'grid',
            ]))
            ->assertOk()
            ->assertSee('note-1.txt')
            ->assertDontSee('image-1.jpg')
            ->assertSee(__('ui.storage.view_grid'))
            ->assertSee(__('ui.storage.sort_label'))
            ->assertSee('Ali Sender')
            ->assertSee('Reza Recipient');
    }

    public function test_personal_storage_can_sort_files_by_name_and_size(): void
    {
        Storage::fake();

        $owner = User::factory()->create([
            'username' => 'sort-owner',
            'email' => 'sort-owner@example.com',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Sort Storage',
            'slug' => 'sort-storage',
            'description' => 'Storage sort plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 31,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($owner, $plan);

        foreach ([
            ['zeta.pdf', 3000],
            ['alpha.pdf', 1000],
            ['middle.pdf', 2000],
        ] as [$name, $size]) {
            SharedFile::query()->create([
                'owner_id' => $owner->id,
                'is_personal_storage' => true,
                'original_name' => $name,
                'stored_name' => $name,
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'size' => $size,
                'storage_path' => 'files/2026/05/'.$name,
                'checksum' => hash('sha256', $name),
                'expires_at' => null,
                'status' => SharedFile::STATUS_ACTIVE,
                'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
            ]);
        }

        $this->actingAs($owner)
            ->get(route('storage.index', ['sort' => 'name_asc']))
            ->assertOk()
            ->assertSeeInOrder(['alpha.pdf', 'middle.pdf', 'zeta.pdf']);

        $this->actingAs($owner)
            ->get(route('storage.index', ['sort' => 'size_desc']))
            ->assertOk()
            ->assertSeeInOrder(['zeta.pdf', 'middle.pdf', 'alpha.pdf']);
    }

    public function test_personal_storage_paginates_filtered_results(): void
    {
        Storage::fake();

        $owner = User::factory()->create([
            'username' => 'page-owner',
            'email' => 'page-owner@example.com',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Paged Storage',
            'slug' => 'paged-storage',
            'description' => 'Storage pagination plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 32,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($owner, $plan);

        foreach (range(1, 13) as $index) {
            SharedFile::query()->create([
                'owner_id' => $owner->id,
                'is_personal_storage' => true,
                'original_name' => sprintf('paged-%02d.pdf', $index),
                'stored_name' => sprintf('paged-%02d.pdf', $index),
                'mime_type' => 'application/pdf',
                'extension' => 'pdf',
                'size' => 1000 + $index,
                'storage_path' => sprintf('files/2026/05/paged-%02d.pdf', $index),
                'checksum' => hash('sha256', (string) $index),
                'expires_at' => null,
                'status' => SharedFile::STATUS_ACTIVE,
                'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
            ]);
        }

        $this->actingAs($owner)
            ->get(route('storage.index', ['sort' => 'name_asc', 'per_page' => 12]))
            ->assertOk()
            ->assertSee('paged-01.pdf')
            ->assertDontSee('paged-13.pdf')
            ->assertSee(__('ui.storage.results_count', ['count' => '13']));

        $this->actingAs($owner)
            ->get(route('storage.index', ['sort' => 'name_asc', 'per_page' => 12, 'page' => 2]))
            ->assertOk()
            ->assertSee('paged-13.pdf')
            ->assertDontSee('paged-01.pdf');
    }

    public function test_workspace_access_role_shows_shared_no_expiry_file_for_both_sender_and_receiver(): void
    {
        Storage::fake();

        $sender = User::factory()->create([
            'username' => 'workspace-sender',
            'email' => 'workspace-sender@example.com',
        ]);

        $receiver = User::factory()->create([
            'username' => 'workspace-receiver',
            'email' => 'workspace-receiver@example.com',
            'allow_receive_no_expiry' => true,
        ]);

        $senderPlan = SubscriptionPlan::query()->create([
            'name' => 'Workspace Sender',
            'slug' => 'workspace-sender-plan',
            'description' => 'Sender with personal workspace',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 40,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        $receiverPlan = SubscriptionPlan::query()->create([
            'name' => 'Workspace Receiver',
            'slug' => 'workspace-receiver-plan',
            'description' => 'Receiver with personal workspace',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 41,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($sender, $senderPlan);
        PlanPolicy::assignPlan($receiver, $receiverPlan);

        $this->actingAs($sender)
            ->post(route('files.store'), [
                'receiver' => $receiver->username,
                'message' => 'Shared forever',
                'file' => UploadedFile::fake()->create('workspace.pdf', 256, 'application/pdf'),
                'expire_option' => 'never',
            ])
            ->assertRedirect(route('conversations.show', $receiver));

        $sharedFile = SharedFile::query()->latest('id')->firstOrFail();

        $this->assertSame($receiver->id, $sharedFile->owner_id);
        $this->assertTrue($sharedFile->is_personal_storage);

        $this->actingAs($sender)
            ->get(route('storage.index', ['scope' => 'sent']))
            ->assertOk()
            ->assertSee('workspace.pdf');

        $this->actingAs($receiver)
            ->get(route('storage.index', ['scope' => 'received']))
            ->assertOk()
            ->assertSee('workspace.pdf');
    }

    public function test_non_owner_can_remove_shared_file_only_from_their_workspace(): void
    {
        Storage::fake();

        $sender = User::factory()->create([
            'username' => 'detach-sender',
            'email' => 'detach-sender@example.com',
        ]);

        $receiver = User::factory()->create([
            'username' => 'detach-receiver',
            'email' => 'detach-receiver@example.com',
            'allow_receive_no_expiry' => true,
        ]);

        $senderPlan = SubscriptionPlan::query()->create([
            'name' => 'Detach Sender',
            'slug' => 'detach-sender-plan',
            'description' => 'Sender workspace plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 42,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        $receiverPlan = SubscriptionPlan::query()->create([
            'name' => 'Detach Receiver',
            'slug' => 'detach-receiver-plan',
            'description' => 'Receiver workspace plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 43,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($sender, $senderPlan);
        PlanPolicy::assignPlan($receiver, $receiverPlan);

        $this->actingAs($sender)
            ->post(route('files.store'), [
                'receiver' => $receiver->username,
                'message' => 'Detach me later',
                'file' => UploadedFile::fake()->create('detach.pdf', 256, 'application/pdf'),
                'expire_option' => 'never',
            ])
            ->assertRedirect(route('conversations.show', $receiver));

        $sharedFile = SharedFile::query()->latest('id')->firstOrFail();

        $this->actingAs($sender)
            ->delete(route('storage.destroy', $sharedFile))
            ->assertSessionHas('status', __('messages.personal_storage.removed_from_workspace'));

        $this->assertDatabaseHas('files', [
            'id' => $sharedFile->id,
            'owner_id' => $receiver->id,
            'status' => SharedFile::STATUS_ACTIVE,
        ]);

        $this->actingAs($sender)
            ->get(route('storage.index', ['scope' => 'sent']))
            ->assertOk()
            ->assertDontSee('detach.pdf');

        $this->actingAs($receiver)
            ->get(route('storage.index', ['scope' => 'received']))
            ->assertOk()
            ->assertSee('detach.pdf');
    }

    public function test_user_can_create_folder_and_move_shared_workspace_file_into_it(): void
    {
        Storage::fake();

        $sender = User::factory()->create([
            'username' => 'folder-sender',
            'email' => 'folder-sender@example.com',
        ]);

        $receiver = User::factory()->create([
            'username' => 'folder-receiver',
            'email' => 'folder-receiver@example.com',
            'allow_receive_no_expiry' => true,
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Folder Workspace',
            'slug' => 'folder-workspace',
            'description' => 'Workspace with folders',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 44,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => true,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($sender, $plan);
        PlanPolicy::assignPlan($receiver, $plan);

        $this->actingAs($sender)
            ->post(route('files.store'), [
                'receiver' => $receiver->username,
                'message' => 'Folder me',
                'file' => UploadedFile::fake()->create('foldered.pdf', 256, 'application/pdf'),
                'expire_option' => 'never',
            ])
            ->assertRedirect(route('conversations.show', $receiver));

        $sharedFile = SharedFile::query()->latest('id')->firstOrFail();

        $this->actingAs($sender)
            ->post(route('storage.folders.store'), [
                'name' => 'Contracts',
            ])
            ->assertSessionHas('status', __('messages.personal_storage.folder_created'));

        $folder = StorageFolder::query()->where('owner_id', $sender->id)->where('name', 'Contracts')->firstOrFail();

        $this->actingAs($sender)
            ->patch(route('storage.folder.update', $sharedFile), [
                'folder_id' => $folder->id,
            ])
            ->assertSessionHas('status', __('messages.personal_storage.moved_to_folder'));

        $this->assertDatabaseHas('file_storage_access', [
            'file_id' => $sharedFile->id,
            'user_id' => $sender->id,
            'folder_id' => $folder->id,
            'context' => FileStorageAccess::CONTEXT_SENT,
        ]);

        $this->actingAs($sender)
            ->get(route('storage.index', ['folder' => (string) $folder->id]))
            ->assertOk()
            ->assertSee('foldered.pdf')
            ->assertSee('Contracts');

        $this->actingAs($sender)
            ->post(route('storage.folders.store'), [
                'name' => 'Signed',
                'parent_id' => $folder->id,
            ])
            ->assertSessionHas('status', __('messages.personal_storage.folder_created'));

        $childFolder = StorageFolder::query()
            ->where('owner_id', $sender->id)
            ->where('parent_id', $folder->id)
            ->where('name', 'Signed')
            ->firstOrFail();

        $this->actingAs($sender)
            ->patch(route('storage.folder.update', $sharedFile), [
                'folder_id' => $childFolder->id,
            ])
            ->assertSessionHas('status', __('messages.personal_storage.moved_to_folder'));

        $this->actingAs($sender)
            ->get(route('storage.index', ['folder' => (string) $folder->id]))
            ->assertOk()
            ->assertSee('foldered.pdf')
            ->assertSee('Signed');

        $this->actingAs($sender)
            ->get(route('storage.index', ['folder' => (string) $childFolder->id]))
            ->assertOk()
            ->assertSee('foldered.pdf');

        $this->actingAs($sender)
            ->patch(route('storage.folder.update', $sharedFile), [
                'new_folder_name' => 'Invoices',
            ])
            ->assertSessionHas('status', __('messages.personal_storage.moved_to_folder'));

        $quickFolder = StorageFolder::query()->where('owner_id', $sender->id)->where('name', 'Invoices')->firstOrFail();

        $this->assertDatabaseHas('file_storage_access', [
            'file_id' => $sharedFile->id,
            'user_id' => $sender->id,
            'folder_id' => $quickFolder->id,
            'context' => FileStorageAccess::CONTEXT_SENT,
        ]);

        $this->actingAs($sender)
            ->patch(route('storage.rename', $sharedFile), [
                'name' => 'renamed-foldered.pdf',
            ])
            ->assertSessionHas('status', __('messages.personal_storage.file_renamed'));

        $this->assertDatabaseHas('files', [
            'id' => $sharedFile->id,
            'original_name' => 'renamed-foldered.pdf',
        ]);

        $this->actingAs($sender)
            ->patch(route('storage.folders.update', $folder), [
                'name' => 'Signed Contracts',
            ])
            ->assertSessionHas('status', __('messages.personal_storage.folder_updated'));

        $this->assertDatabaseHas('storage_folders', [
            'id' => $folder->id,
            'owner_id' => $sender->id,
            'name' => 'Signed Contracts',
        ]);

        $this->actingAs($sender)
            ->delete(route('storage.folders.destroy', $folder))
            ->assertSessionHasErrors('folder');

        $this->actingAs($sender)
            ->post(route('storage.folders.store'), [
                'name' => 'Empty folder',
            ])
            ->assertSessionHas('status', __('messages.personal_storage.folder_created'));

        $emptyFolder = StorageFolder::query()->where('owner_id', $sender->id)->where('name', 'Empty folder')->firstOrFail();

        $this->actingAs($sender)
            ->delete(route('storage.folders.destroy', $emptyFolder))
            ->assertSessionHas('status', __('messages.personal_storage.folder_deleted'));

        $this->assertDatabaseMissing('storage_folders', [
            'id' => $emptyFolder->id,
        ]);
    }

    public function test_user_can_bulk_move_manageable_workspace_files_to_folder(): void
    {
        Storage::fake();

        $sender = User::factory()->create([
            'username' => 'bulk-sender',
            'email' => 'bulk-sender@example.com',
        ]);

        $receiver = User::factory()->create([
            'username' => 'bulk-receiver',
            'email' => 'bulk-receiver@example.com',
            'allow_receive_no_expiry' => true,
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Bulk Folder Workspace',
            'slug' => 'bulk-folder-workspace',
            'description' => 'Folder bulk workspace plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 46,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => true,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($sender, $plan);
        PlanPolicy::assignPlan($receiver, $plan);

        $files = collect(['bulk-a.pdf', 'bulk-b.pdf'])
            ->map(function (string $name) use ($sender, $receiver): SharedFile {
                $file = SharedFile::query()->create([
                    'owner_id' => $sender->id,
                    'is_personal_storage' => true,
                    'original_name' => $name,
                    'stored_name' => $name,
                    'mime_type' => 'application/pdf',
                    'extension' => 'pdf',
                    'size' => 1000,
                    'storage_path' => 'files/2026/05/'.$name,
                    'checksum' => hash('sha256', $name),
                    'expires_at' => null,
                    'status' => SharedFile::STATUS_ACTIVE,
                    'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
                ]);

                FileSend::query()->create([
                    'file_id' => $file->id,
                    'sender_id' => $sender->id,
                    'receiver_id' => $receiver->id,
                    'message' => 'bulk',
                ]);

                FileStorageAccess::query()->create([
                    'file_id' => $file->id,
                    'user_id' => $sender->id,
                    'role' => FileStorageAccess::ROLE_OWNER,
                    'context' => FileStorageAccess::CONTEXT_SENT,
                ]);

                return $file;
            });

        $folder = StorageFolder::query()->create([
            'owner_id' => $sender->id,
            'parent_id' => null,
            'name' => 'Bulk Docs',
        ]);

        $this->actingAs($sender)
            ->patch(route('storage.bulk.folder'), [
                'file_ids' => $files->pluck('id')->all(),
                'folder_id' => $folder->id,
            ])
            ->assertSessionHas('status', __('messages.personal_storage.bulk_moved_to_folder', ['count' => 2]));

        foreach ($files as $file) {
            $this->assertDatabaseHas('file_storage_access', [
                'file_id' => $file->id,
                'user_id' => $sender->id,
                'folder_id' => $folder->id,
            ]);
        }
    }

    public function test_user_can_bulk_remove_selected_workspace_files(): void
    {
        Storage::fake();

        $user = User::factory()->create([
            'username' => 'bulk-remove-user',
            'email' => 'bulk-remove@example.com',
        ]);

        $otherUser = User::factory()->create([
            'username' => 'bulk-remove-other',
            'email' => 'bulk-remove-other@example.com',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Bulk Remove Workspace',
            'slug' => 'bulk-remove-workspace',
            'description' => 'Bulk remove workspace plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 47,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($user, $plan);
        PlanPolicy::assignPlan($otherUser, $plan);

        Storage::put('files/2026/05/owned-bulk-remove.pdf', 'owned');

        $ownedFile = SharedFile::query()->create([
            'owner_id' => $user->id,
            'is_personal_storage' => true,
            'original_name' => 'owned-bulk-remove.pdf',
            'stored_name' => 'owned-bulk-remove.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 1000,
            'storage_path' => 'files/2026/05/owned-bulk-remove.pdf',
            'checksum' => hash('sha256', 'owned-bulk-remove'),
            'expires_at' => null,
            'status' => SharedFile::STATUS_ACTIVE,
            'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
        ]);

        $sharedFile = SharedFile::query()->create([
            'owner_id' => $otherUser->id,
            'is_personal_storage' => true,
            'original_name' => 'shared-bulk-remove.pdf',
            'stored_name' => 'shared-bulk-remove.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 1000,
            'storage_path' => 'files/2026/05/shared-bulk-remove.pdf',
            'checksum' => hash('sha256', 'shared-bulk-remove'),
            'expires_at' => null,
            'status' => SharedFile::STATUS_ACTIVE,
            'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
        ]);

        FileStorageAccess::query()->create([
            'file_id' => $sharedFile->id,
            'user_id' => $user->id,
            'role' => FileStorageAccess::ROLE_MANAGER,
            'context' => FileStorageAccess::CONTEXT_RECEIVED,
        ]);

        $this->actingAs($user)
            ->delete(route('storage.bulk.destroy'), [
                'file_ids' => [$ownedFile->id, $sharedFile->id],
            ])
            ->assertSessionHas('status', __('messages.personal_storage.bulk_removed_from_workspace', ['count' => 2]));

        $this->assertDatabaseHas('files', [
            'id' => $ownedFile->id,
            'status' => SharedFile::STATUS_DELETED,
        ]);
        Storage::assertMissing('files/2026/05/owned-bulk-remove.pdf');

        $this->assertDatabaseHas('files', [
            'id' => $sharedFile->id,
            'status' => SharedFile::STATUS_ACTIVE,
        ]);
        $this->assertDatabaseMissing('file_storage_access', [
            'file_id' => $sharedFile->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_user_can_star_and_filter_workspace_files(): void
    {
        Storage::fake();

        $user = User::factory()->create([
            'username' => 'star-user',
            'email' => 'star-user@example.com',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Star Workspace',
            'slug' => 'star-workspace',
            'description' => 'Star workspace plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 48,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => false,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($user, $plan);

        $starredFile = SharedFile::query()->create([
            'owner_id' => $user->id,
            'is_personal_storage' => true,
            'original_name' => 'important-contract.pdf',
            'stored_name' => 'important-contract.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 1000,
            'storage_path' => 'files/2026/05/important-contract.pdf',
            'checksum' => hash('sha256', 'important-contract'),
            'expires_at' => null,
            'status' => SharedFile::STATUS_ACTIVE,
            'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
        ]);

        SharedFile::query()->create([
            'owner_id' => $user->id,
            'is_personal_storage' => true,
            'original_name' => 'ordinary-file.pdf',
            'stored_name' => 'ordinary-file.pdf',
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => 1000,
            'storage_path' => 'files/2026/05/ordinary-file.pdf',
            'checksum' => hash('sha256', 'ordinary-file'),
            'expires_at' => null,
            'status' => SharedFile::STATUS_ACTIVE,
            'security_scan_status' => SharedFile::SECURITY_SCAN_CLEAN,
        ]);

        $this->actingAs($user)
            ->patch(route('storage.star', $starredFile))
            ->assertSessionHas('status', __('messages.personal_storage.star_updated'));

        $this->assertDatabaseHas('file_storage_access', [
            'file_id' => $starredFile->id,
            'user_id' => $user->id,
            'is_starred' => true,
        ]);

        $this->actingAs($user)
            ->get(route('storage.index', ['starred' => 'yes']))
            ->assertOk()
            ->assertSee('important-contract.pdf')
            ->assertDontSee('ordinary-file.pdf')
            ->assertSee(__('ui.storage.starred_badge'));
    }

    public function test_user_can_reparent_folder_without_creating_cycles(): void
    {
        $user = User::factory()->create([
            'username' => 'folder-reparent-user',
            'email' => 'folder-reparent@example.com',
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Reparent Folder Workspace',
            'slug' => 'reparent-folder-workspace',
            'description' => 'Folder reparent workspace plan',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 47,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => true,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($user, $plan);

        $parent = StorageFolder::query()->create([
            'owner_id' => $user->id,
            'parent_id' => null,
            'name' => 'Parent',
        ]);
        $child = StorageFolder::query()->create([
            'owner_id' => $user->id,
            'parent_id' => $parent->id,
            'name' => 'Child',
        ]);
        $grandchild = StorageFolder::query()->create([
            'owner_id' => $user->id,
            'parent_id' => $child->id,
            'name' => 'Grandchild',
        ]);

        $this->actingAs($user)
            ->patch(route('storage.folders.update', $child), [
                'name' => 'Child',
                'parent_id' => null,
            ])
            ->assertSessionHas('status', __('messages.personal_storage.folder_updated'));

        $this->assertDatabaseHas('storage_folders', [
            'id' => $child->id,
            'parent_id' => null,
        ]);

        $child->forceFill(['parent_id' => $parent->id])->save();
        $grandchild->forceFill(['parent_id' => $child->id])->save();

        $this->actingAs($user)
            ->patch(route('storage.folders.update', $parent), [
                'name' => 'Parent',
                'parent_id' => $grandchild->id,
            ])
            ->assertSessionHasErrors('folder');

        $this->assertDatabaseHas('storage_folders', [
            'id' => $parent->id,
            'parent_id' => null,
        ]);
    }

    public function test_conversation_page_shows_workspace_files_for_that_exchange(): void
    {
        Storage::fake();

        $sender = User::factory()->create([
            'username' => 'exchange-sender',
            'email' => 'exchange-sender@example.com',
        ]);

        $receiver = User::factory()->create([
            'username' => 'exchange-receiver',
            'email' => 'exchange-receiver@example.com',
            'allow_receive_no_expiry' => true,
        ]);

        $plan = SubscriptionPlan::query()->create([
            'name' => 'Exchange Workspace',
            'slug' => 'exchange-workspace',
            'description' => 'Workspace visible inside exchange',
            'is_active' => true,
            'is_default' => false,
            'sort_order' => 45,
            'max_upload_size_mb' => 10,
            'max_storage_mb' => 10,
            'expire_options' => ['default', '24', 'never'],
            'allow_public_links' => true,
            'allow_password_protection' => true,
            'allow_custom_expiry' => false,
            'allow_never_expire' => true,
            'allow_personal_storage' => true,
            'allow_team_features' => false,
            'allow_signature_workflow' => false,
            'allow_folders' => true,
            'allow_ai_features' => false,
        ]);

        PlanPolicy::assignPlan($sender, $plan);
        PlanPolicy::assignPlan($receiver, $plan);

        $this->actingAs($sender)
            ->post(route('files.store'), [
                'receiver' => $receiver->username,
                'message' => 'Shown in exchange workspace',
                'file' => UploadedFile::fake()->image('exchange-shot.jpg', 20, 20),
                'expire_option' => 'never',
            ])
            ->assertRedirect(route('conversations.show', $receiver));

        $this->actingAs($sender)
            ->get(route('conversations.show', $receiver))
            ->assertOk()
            ->assertSee(__('ui.exchange.workspace_title'))
            ->assertSee('exchange-shot.jpg')
            ->assertSee(__('ui.storage.no_expiry'));
    }
}
