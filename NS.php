<?php

/**
 * NS System script
 *
 * Copyright 2016-2020 Jerry Shaw <jerry-shaw@live.com>
 * Copyright 2016-2020 秋水之冰 <27206617@qq.com>
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

//Strict type declare
declare(strict_types = 1);

//Require PHP version >= 7.4.0
if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    exit('NervSys needs PHP 7.4.0 or higher!');
}

//Define NervSys version
define('NS_VER', '8.0.0 Alpha');

//Define SYSTEM ROOT path
define('NS_ROOT', __DIR__);

//Define JSON formats
define('JSON_FORMAT', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS);
define('JSON_PRETTY', JSON_FORMAT | JSON_PRETTY_PRINT);

//Detect extension support
define('SPT_OPC', extension_loaded('Zend OPcache'));
define('SPT_SHM', extension_loaded('shmop'));

//Autoload function
function Autoload(string $class_name, string $root_path = NS_ROOT): void
{
    //Get relative path of class file
    $file_name = strtr($class_name, '\\', DIRECTORY_SEPARATOR) . '.php';

    //Skip non-existent class file
    if (!is_file($class_file = $root_path . DIRECTORY_SEPARATOR . $file_name)) {
        return;
    }

    //Compile/require class file
    $file_compiled = false;

    if (SPT_OPC && 0 === strpos($class_file, NS_ROOT)) {
        $file_compiled = opcache_compile_file($class_file);
    }

    if (!$file_compiled) {
        require $class_file;
    }

    unset($class_name, $root_path, $file_name, $class_file, $file_compiled);
}

//Compile/require Factory module
Autoload(\Core\Factory::class);

//Register autoload (NS_ROOT based)
spl_autoload_register(
    static function (string $class_name): void
    {
        Autoload($class_name);
    }
);

/**
 * Class NS
 */
class NS
{
    /**
     * NS constructor.
     */
    public function __construct()
    {
        //Misc settings
        set_time_limit(0);
        ignore_user_abort(true);

        //Set error_reporting level
        error_reporting(E_ALL);

        //todo CORS detection


        //Init Error library
        $Error = \Core\Lib\Error::new();

        //Register error handler
        register_shutdown_function($Error->shutdown_handler);
        set_exception_handler($Error->exception_handler);
        set_error_handler($Error->error_handler);

        //Init App library
        $App = \Core\Lib\App::new();

        //Set include path
        set_include_path($App->root_path . DIRECTORY_SEPARATOR . $App->inc_path);

        //Set default timezone
        date_default_timezone_set($App->timezone);

        //Input date parser
        $IOUnit = \Core\Lib\IOUnit::new();

        //Call data reader handler
        call_user_func($App->is_cli ? $IOUnit->cli_handler : $IOUnit->cgi_handler);


    }
}