<?php

namespace Ctrlweb\BadgeFactor2\Console\Commands;

use Ctrlweb\BadgeFactor2\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MigrateWordPressData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bf2:migrate-wp-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrates data from a WordPress site to Laravel.';

    /**
     * WordPress Database
     *
     * @var string
     */
    protected $wordpressDb;

    /**
     * WordPress table prefix
     *
     * @var string
     */
    protected $prefix;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->wordpressDb = config('badgefactor2.wordpress.connection');
        $this->prefix = config('badgefactor2.wordpress.db_prefix');

        $this->migrateUsers($this->wordpressDb, $this->prefix);


        $this->info("\nAll done!");
    }

    /**
     * Migrate WordPress users;
     *
     * @return void
     */
    private function migrateUsers($wordpressDb, $prefix)
    {
        $this->info('Migrating users...');

        // Identify WP learners.
        $users = $this->withProgressBar(
            DB::connection($wordpressDb)
            ->select(
                "SELECT DISTINCT u.*
                FROM {$prefix}users u
                WHERE u.user_status = 0"
            ), function ($wpUser) use ($wordpressDb, $prefix) {
                $userMeta = collect(
                    DB::connection($wordpressDb)
                        ->select(
                            "SELECT *
                                FROM {$prefix}usermeta
                                WHERE user_id = ?",
                            [$wpUser->ID]
                        )
                );

                // Create user.
                $user = User::updateOrCreate(
                    [
                        'email' => $wpUser->user_email,
                    ],
                    [
                        'name'        => $wpUser->display_name,
                        'first_name'  => $userMeta->firstWhere('meta_key', 'first_name')->meta_value,
                        'last_name'   => $userMeta->firstWhere('meta_key', 'last_name')->meta_value,
                        'description' => $userMeta->firstWhere('meta_key', 'description')->meta_value,
                        'website'     => '',
                        'slug'        => $wpUser->user_nicename,
                        'password'    => Hash::make($wpUser->user_pass),
                        'created_at'  => Carbon::parse($wpUser->user_registered)
                            ->setTimeZone(config('app.timezone'))
                            ->toDateTimeString(),
                        'wp_id'       => $wpUser->ID,
                        'wp_password' => $wpUser->user_pass,
                    ]
                );


                // Identify and transfer WordPress capabilities.
                if ($userMeta->firstwhere('meta_key', 'wp_capabilities')->meta_value) {
                    $capabilities = \unserialize($userMeta->firstwhere('meta_key', 'wp_capabilities')->meta_value);
                    if (array_key_exists('administrator', $capabilities) && $capabilities['administrator'] === true) {
                        $user->roles()->updateOrCreate(['role' => 'admin']);
                    }
                    if (array_key_exists('customer', $capabilities) && $capabilities['customer'] === true) {
                        $wcOrders = DB::connection($wordpressDb)
                            ->select(
                                "SELECT p.* from {$prefix}posts p
                                JOIN {$prefix}postmeta pm
                                ON p.ID = pm.post_id
                                WHERE post_type = 'shop_order'
                                AND meta_key = '_customer_user'
                                AND meta_value = '{$user->wp_id}'"
                            );

                        foreach ($wcOrders as $wcOrder) {
                            $wcOrderMeta = collect(
                                DB::connection($wordpressDb)
                                    ->select(
                                        "SELECT *
                                            FROM {$prefix}postmeta
                                            WHERE post_id = ?",
                                        [$wcOrder->ID]
                                    )
                            );

                            if (0 === intval($wcOrderMeta->firstWhere('meta_key', '_order_total')->meta_value)) {
                                // Free access: give learner-free role.
                                $user->roles()->updateOrCreate(['role' => 'learner-free']);
                            } else {
                                // Give access to specific courses.

                            }
                        }
                    }
                }
            }
        );

    }


}
