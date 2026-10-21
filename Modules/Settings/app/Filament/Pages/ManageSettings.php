<?php

namespace Modules\Settings\app\Filament\Pages;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Exception;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Actions\Action;
use Modules\Settings\app\Models\Setting;
use Modules\Settings\Services\SettingService;
use Filament\Notifications\Notification;

class ManageSettings extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cog-8-tooth';

    protected string $view = 'settings::filament.pages.manage-settings';

    public ?array $data = [];

    /**
     * Same permission as the settings API routes. Also hides the navigation item.
     */
    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can('manage-settings') ?? false;
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings');
    }

    

    public function mount(): void
    {
        // One query for all settings (previously one extra query per setting).
        $this->data = [];

        foreach (Setting::all(['key', 'value', 'type']) as $setting) {
            $value = $setting->getRawOriginal('value');

            $this->data[$setting->key] = match (true) {
                $value === 'true' => true,
                $value === 'false' => false,
                $setting->type === 'json' => json_decode((string) $value, true),
                default => $value,
            };
        }

        $this->form->fill($this->data);
    }

    public function saveAction(): Action
    {
        return Action::make('save')
            ->label(__('admin.save_changes'))
            ->submit('save');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Settings')
                    ->tabs([
                        Tab::make(__('admin.contact_info'))
                            ->icon('heroicon-m-phone')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('app_name')->label(__('admin.keys.app_name'))->required(),
                                    TextInput::make('contact_email')->label(__('admin.keys.contact_email'))->email(),
                                    TextInput::make('contact_phone')->label(__('admin.keys.contact_phone')),
                                    TextInput::make('contact_whatsapp')->label(__('admin.keys.contact_whatsapp')),
                                    TextInput::make('contact_map_location')->label(__('admin.keys.map_url'))->columnSpanFull(),
                                ]),
                            ]),

                        Tab::make(__('admin.social_media'))
                            ->icon('heroicon-m-share')
                            ->schema([
                                Grid::make(2)->schema([
                                    TextInput::make('social_facebook')->url()->prefix('https://'),
                                    TextInput::make('social_instagram')->url()->prefix('https://'),
                                    TextInput::make('social_twitter')->url()->prefix('https://'),
                                    TextInput::make('social_linkedin')->url()->prefix('https://'),
                                    TextInput::make('social_snapchat')->url()->prefix('https://'),
                                    TextInput::make('social_tiktok')->url()->prefix('https://'),
                                ]),
                            ]),

                        Tab::make('SEO')
                            ->icon('heroicon-m-globe-alt')
                            ->schema([
                                Section::make('Arabic SEO')->schema([
                                    TextInput::make('meta_title_ar'),
                                    Textarea::make('meta_description_ar')->rows(3),
                                ]),
                                Section::make('English SEO')->schema([
                                    TextInput::make('meta_title_en'),
                                    Textarea::make('meta_description_en')->rows(3),
                                ]),
                                TextInput::make('meta_keywords'),
                            ]),

                        Tab::make(__('admin.system'))
                            ->icon('heroicon-m-cpu-chip')
                            ->schema([
                                Grid::make(2)->schema([
                                    Toggle::make('maintenance_mode')->label(__('admin.keys.maintenance')),
                                    TextInput::make('default_currency'),
                                    TextInput::make('google_analytics_id'),
                                ]),
                            ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            foreach ($data as $key => $value) {
                $finalValue = $value;
                if (is_bool($value)) $finalValue = $value ? 'true' : 'false';
                if (is_array($value)) $finalValue = json_encode($value);

                // Create missing rows too; a plain update silently dropped values for unseeded keys.
                $setting = Setting::firstOrNew(['key' => $key]);

                if (! $setting->exists) {
                    $setting->type = match (true) {
                        is_bool($value) => 'boolean',
                        is_array($value) => 'json',
                        default => 'string',
                    };
                    $setting->is_public = true;
                }

                $setting->value = $finalValue;
                $setting->save();
            }

            // The API serves settings from a forever cache; drop it so panel edits show up.
            app(SettingService::class)->flushCache();

            Notification::make()
                ->title(__('admin.saved_successfully'))
                ->success()
                ->send();

        } catch (Exception $e) {
            Notification::make()
                ->title('Error')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getTitle(): string
    {
        return __('admin.settings');
    }
}