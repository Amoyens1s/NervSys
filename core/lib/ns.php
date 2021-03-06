<?php

/**
 * NS System Core Library
 *
 * Copyright 2016-2019 Jerry Shaw <jerry-shaw@live.com>
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

namespace core\lib;

use core\lib\stc\error;
use core\lib\stc\factory;
use core\lib\std\io;
use core\lib\std\pool;
use core\lib\std\router;

/**
 * Class ns
 *
 * @package core\lib
 */
final class ns
{
    /** @var \core\lib\std\pool $unit_pool */
    private $unit_pool;

    /**
     * ns constructor.
     */
    public function __construct()
    {
        /** @var \core\lib\std\pool unit_pool */
        $this->unit_pool = factory::build(pool::class);

        /** @var \core\lib\std\router $unit_router */
        $unit_router = factory::build(router::class);

        /** @var \core\lib\cgi $unit_cgi */
        $unit_cgi = factory::build(cgi::class);

        /** @var \core\lib\cli $unit_cli */
        $unit_cli = factory::build(cli::class);

        /** @var \core\lib\std\io $unit_io */
        $unit_io = factory::build(io::class);

        //Load app.ini
        $conf = $this->load_ini();

        //Set default timezone
        date_default_timezone_set($conf['sys']['timezone']);

        //Verify CORS in CGI mode
        if (!$this->unit_pool->is_CLI && !$this->pass_cors($conf['cors'])) {
            exit(0);
        }

        //Run INIT section (ONLY CGI)
        foreach ($this->unit_pool->conf['init'] as $value) {
            try {
                //Call INIT functions using default router
                $this->unit_pool->result += $unit_cgi->call_group($unit_router->parse($value));
            } catch (\Throwable $throwable) {
                error::exception_handler($throwable);
                $unit_io->output($this->unit_pool);
                unset($throwable);
                exit(0);
            }
        }

        //Read input data
        if ($this->unit_pool->is_CLI) {
            //Read arguments
            $data_argv = $unit_io->read_argv();

            //Copy to pool
            $this->unit_pool->cli_param['argv'] = &$data_argv['a'];
            $this->unit_pool->cli_param['pipe'] = &$data_argv['p'];
        } else {
            //Read CMD from URL
            $url_cmd = $unit_io->read_url();

            //Read data package
            $data_pack = $unit_io->read_http() + $unit_io->read_input(file_get_contents('php://input'));

            //Merge arguments
            $data_argv = [
                'c' => '' !== $url_cmd ? $url_cmd : ($data_pack['c'] ?? ''),
                'r' => $data_pack['r'] ?? 'json',
                'd' => &$data_pack
            ];

            unset($url_cmd, $data_pack);
        }

        //Copy to pool
        $this->unit_pool->cmd = &$data_argv['c'];
        $this->unit_pool->ret = &$data_argv['r'];

        //Add input data
        $this->unit_pool->data += $data_argv['d'];

        //Parse input command
        if (!empty($this->unit_pool->cgi_stack = $unit_router->parse($data_argv['c']))) {
            $this->unit_pool->result += $unit_cgi->call_service();
        }

        //Proceed CLI once CMD can be parsed
        if ($this->unit_pool->is_CLI && !empty($this->unit_pool->cli_stack = $unit_router->cli_get_trust($data_argv['c'], $this->unit_pool->conf['cli']))) {
            $this->unit_pool->result += $unit_cli->call_program();
        }

        //Output data
        $unit_io->output($this->unit_pool);
        unset($unit_router, $unit_cgi, $unit_cli, $unit_io, $conf, $value, $data_argv);
    }

    /**
     * Load app.ini
     */
    private function load_ini(): array
    {
        if (is_file($app_ini = ROOT . DIRECTORY_SEPARATOR . APP_PATH . DIRECTORY_SEPARATOR . 'app.ini')) {
            //Parse "app.ini"
            $app_conf = parse_ini_file($app_ini, true, INI_SCANNER_TYPED);

            //Update conf values
            foreach ($app_conf as $key => $value) {
                $this->unit_pool->conf[$key = strtolower($key)] = array_replace_recursive($this->unit_pool->conf[$key], $value);
            }

            unset($app_conf, $key, $value);
        }

        unset($app_ini);
        return $this->unit_pool->conf;
    }

    /**
     * Check CORS permission
     *
     * @param array $cors_conf
     *
     * @return bool
     */
    private function pass_cors(array $cors_conf): bool
    {
        //Server ENV passed
        if (!isset($_SERVER['HTTP_ORIGIN'])
            || $_SERVER['HTTP_ORIGIN'] === ($this->unit_pool->is_TLS ? 'https://' : 'http://') . $_SERVER['HTTP_HOST']) {
            return true;
        }

        //Access NOT allowed
        if (is_null($allow_headers = $cors_conf[$_SERVER['HTTP_ORIGIN']] ?? $cors_conf['*'] ?? null)) {
            http_response_code(406);
            return false;
        }

        //Response allowed headers
        header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
        header('Access-Control-Allow-Headers: ' . $allow_headers);
        header('Access-Control-Allow-Credentials: true');

        //Exit OPTION request
        if ('OPTIONS' === $_SERVER['REQUEST_METHOD']) {
            return false;
        }

        //All passed
        unset($cors_conf, $allow_headers);
        return true;
    }
}