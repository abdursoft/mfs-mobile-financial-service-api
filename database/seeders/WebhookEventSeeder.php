<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WebhookEventSeeder extends Seeder
{
    public function run()
    {
        $webhooks = [
            [
                'hook_name' => 'payment_intent',
                'children' => [
                    'payment_intent.created',
                    'payment_intent.succeeded',
                    'payment_intent.payment_failed',
                ],
            ],
            [
                'hook_name' => 'charge',
                'children' => [
                    'charge.created',
                    'charge.succeeded',
                    'charge.updated',
                    'charge.failed',
                ],
            ],

            [
                'hook_name' => 'refund',
                'children' => [
                    'refund.created',
                    'refund.updated',
                    'refund.failed',
                ],
            ],

            [
                'hook_name' => 'customer',
                'children' => [
                    'customer.created',
                    'customer.updated',
                    'customer.deleted',
                ],
            ],
            [
                'hook_name' => 'price',
                'children' => [
                    'price.created',
                    'price.updated',
                    'price.deleted',
                ]
            ],

            [
                'hook_name' => 'product',
                'children' => [
                    'product.created',
                    'product.updated',
                    'product.deleted',
                ],
            ],

            [
                'hook_name' => 'invoice',
                'children' => [
                    'invoice.created',
                    'invoice.finalized',
                    'invoice.payment_succeeded',
                    'invoice.payment_failed',
                ],
            ],

            [
                'hook_name' => 'subscription',
                'children' => [
                    'customer.subscription.created',
                    'customer.subscription.updated',
                    'customer.subscription.deleted',
                ],
            ],

            [
                'hook_name' => 'payout',
                'children' => [
                    'payout.created',
                    'payout.failed',
                    'payout.paid',
                ],
            ],
            
            [
                'hook_name' => 'topup',
                'children' => [
                    'topup.created',
                    'topup.failed',
                    'topup.succeeded',
                ],
            ],
        ];

        $order = 1;

        foreach ($webhooks as $group) {
            // Insert parent
            $parentId = DB::table('webhook_events')->insertGetId([
                'hook_name' => $group['hook_name'],
                'order'     => $order++,
                'type'      => 'parent',
                'parent_id' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Insert children
            foreach ($group['children'] as $child) {
                DB::table('webhook_events')->insert([
                    'hook_name' => $child,
                    'order'     => $order++,
                    'type'      => 'child',
                    'parent_id' => $parentId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
