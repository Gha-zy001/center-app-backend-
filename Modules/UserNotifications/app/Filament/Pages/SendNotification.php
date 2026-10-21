<?php

namespace Modules\UserNotifications\app\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Radio;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use Modules\User\app\Models\User;
use Spatie\Permission\Models\Role;
use Modules\UserNotifications\app\Jobs\SendBulkNotification;

class SendNotification extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-paper-airplane';

    protected string $view = 'usernotifications::filament.pages.send-notification';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'send_to' => 'all',
        ]);
    }

    /**
     * Broadcasting to every user is a privileged action. Also hides the navigation item.
     */
    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can('send-notifications') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.send_notification');
    }

    public function sendAction(): Action
    {
        return Action::make('send')
            ->label(__('admin.send'))
            ->color('primary')
            ->icon('heroicon-m-paper-airplane')
            ->requiresConfirmation()
            ->modalHeading(__('admin.confirm_notification_send'))
            ->modalDescription(__('admin.confirm_notification_description'))
            ->action(fn () => $this->send());
    }

    public function getTitle(): string
    {
        return __('admin.send_notification');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->schema([
                        Radio::make('send_to')
                            ->label(__('admin.keys.send_to'))
                            ->options([
                                'all' => __('admin.keys.all_users'),
                                'users' => __('admin.keys.specific_users'),
                                'roles' => __('admin.keys.specific_roles'),
                            ])
                            ->live()
                            ->inline(),

                        Select::make('users')
                            ->label(__('admin.keys.select_users'))
                            ->multiple()
                            ->options(User::pluck('name', 'id'))
                            ->searchable()
                            ->visible(fn ($get) => $get('send_to') === 'users')
                            ->required(fn ($get) => $get('send_to') === 'users'),

                        Select::make('roles')
                            ->label(__('admin.keys.select_roles'))
                            ->multiple()
                            ->options(Role::where('guard_name', 'web')->pluck('name', 'name'))
                            ->preload()
                            ->visible(fn ($get) => $get('send_to') === 'roles')
                            ->required(fn ($get) => $get('send_to') === 'roles'),

                        TextInput::make('title')
                            ->label(__('admin.keys.title'))
                            ->required(),

                        Textarea::make('message')
                            ->label(__('admin.keys.message'))
                            ->required()
                            ->rows(5),
                    ]),
            ]);
    }

    public function send(): void
    {
        $formData = $this->form->getState();

        $query = User::query();

        if ($formData['send_to'] === 'users') {
            $query->whereIn('id', $formData['users'] ?? []);
        } elseif ($formData['send_to'] === 'roles') {
            $query->role($formData['roles'] ?? []);
        }

        if (! $query->exists()) {
            Notification::make()
                ->title(__('admin.no_users_found'))
                ->danger()
                ->send();
            return;
        }

        // Sending inline (one DB insert + one FCM call per user) times out for large audiences,
        // so recipients are queued in chunks and delivered by the queue worker.
        $query->select('id')->chunkById(200, function ($users) use ($formData) {
            SendBulkNotification::dispatch(
                $users->pluck('id')->all(),
                $formData['title'],
                $formData['message'],
            );
        });

        Notification::make()
            ->title(__('admin.sent_successfully'))
            ->success()
            ->send();

        $this->form->fill(['send_to' => 'all']);
    }
}