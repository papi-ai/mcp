<?php

/*
 * This file is part of PapiAI,
 * A simple but powerful PHP library for building AI agents.
 *
 * (c) Marcello Duarte <marcello.duarte@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

use PapiAI\Mcp\ToolName;

describe('ToolName', function () {
    it('keeps a name every provider accepts', function () {
        expect((new ToolName('get_weather'))->exposed())->toBe('get_weather');
    });

    it('puts the prefix in front', function () {
        expect((new ToolName('get_weather', 'weather'))->exposed())->toBe('weather_get_weather');
    });

    it('replaces the characters providers refuse', function () {
        expect((new ToolName('admin.users.list', 'crm'))->exposed())->toBe('crm_admin_users_list');
    });

    it('shortens a long name to 64 characters, ending in a hash that keeps it unique', function () {
        $first = (new ToolName(str_repeat('a', 70) . '_first'))->exposed();
        $second = (new ToolName(str_repeat('a', 70) . '_second'))->exposed();

        expect(strlen($first))->toBe(64);
        expect($first)->toMatch('/^a+_[0-9a-f]{8}$/');
        expect($first)->not->toBe($second);
        expect((new ToolName(str_repeat('a', 70) . '_first'))->exposed())->toBe($first);
    });
});
