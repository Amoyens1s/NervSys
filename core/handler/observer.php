<?php

/**
 * Observer Handler
 *
 * Copyright 2016-2018 秋水之冰 <27206617@qq.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace core\handler;

use core\parser\cmd;
use core\parser\input;
use core\parser\setting;

use core\pool\config;
use core\pool\unit;

class observer
{
    /**
     * Observer start
     */
    public static function start(): void
    {
        //Load config settings
        setting::load();

        //Check CORS permissions
        self::chk_cors();

        //Check observer status
        if (self::stop(true)) {
            return;
        }

        //Call INIT commands
        if (!empty(config::$INIT)) {
            operator::init_load(config::$INIT);
        }

        //Check observer status
        if (self::stop(true)) {
            return;
        }

        //Prepare input
        input::prep();

        //Prepare cmd
        cmd::prep();

        //Run cgi process
        operator::run_cgi();

        //Check observer status
        if (self::stop(true)) {
            return;
        }

        //Run cli process
        if (!config::$IS_CGI) {
            operator::run_cli();
        }
    }

    /**
     * Send signal
     *
     * @param int $signal
     */
    public static function send(int $signal): void
    {
        unit::$signal = &$signal;
        unset($signal);
    }

    /**
     * Stop observer
     *
     * @param bool $log
     *
     * @return bool
     */
    public static function stop(bool $log = false): bool
    {
        if (0 === unit::$signal) {
            return false;
        } else {
            if ($log) {
                //Log observer status
                logger::log('info', config::$SIGNAL[unit::$signal] ?? 'Process Terminated!');
            }

            return true;
        }
    }

    /**
     * Collect results
     *
     * @return string
     */
    public static function collect(): string
    {
        //Build result
        $count = count(unit::$result);
        $result = 0 === $count ? '' : (1 === $count ? current(unit::$result) : unit::$result);

        //Build json output
        $output = !empty(unit::$error) ? unit::$error + ['data' => &$result] : $result;
        $json = json_encode($output, 0 === error::$level ? 3906 : 4034);

        if (!config::$IS_CGI) {
            $json .= PHP_EOL;
        }

        unset($count, $result, $output);
        return $json;
    }

    /**
     * Get IP
     *
     * @return string
     */
    public static function get_ip(): string
    {
        //IP check list
        $chk_list = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        //Check ip values
        foreach ($chk_list as $key) {
            if (!isset($_SERVER[$key])) {
                continue;
            }

            $ip_list = false !== strpos($_SERVER[$key], ',') ? explode(',', $_SERVER[$key]) : [$_SERVER[$key]];

            foreach ($ip_list as $ip) {
                $ip = filter_var(trim($ip), FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_IPV6);

                if (false !== $ip) {
                    unset($chk_list, $key, $ip_list);
                    return $ip;
                }
            }
        }

        unset($chk_list, $key, $ip_list, $ip);
        return 'unknown';
    }

    /**
     * Check Cross-origin resource sharing permission
     */
    private static function chk_cors(): void
    {
        if (
            empty(config::$CORS)
            || !isset($_SERVER['HTTP_ORIGIN'])
            || $_SERVER['HTTP_ORIGIN'] === (config::$IS_HTTPS ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']
        ) {
            return;
        }

        if (!isset(config::$CORS[$_SERVER['HTTP_ORIGIN']])) {
            logger::log('info', 'CORS denied for ' . $_SERVER['HTTP_ORIGIN'] . ' from ' . self::get_ip());
            self::send(1);
        }

        //Response Access-Control-Allow-Origin & Access-Control-Allow-Headers
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: ' . config::$CORS[$_SERVER['HTTP_ORIGIN']]);

        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            self::send(1);
        }
    }
}