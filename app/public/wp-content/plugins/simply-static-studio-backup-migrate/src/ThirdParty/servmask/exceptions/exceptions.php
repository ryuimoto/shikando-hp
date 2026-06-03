<?php
/**
 * Copyright (C) 2014-2025 ServMask Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Attribution: This code is part of the All-in-One WP Migration plugin, developed by
 *
 * ███████╗███████╗██████╗ ██╗   ██╗███╗   ███╗ █████╗ ███████╗██╗  ██╗
 * ██╔════╝██╔════╝██╔══██╗██║   ██║████╗ ████║██╔══██╗██╔════╝██║ ██╔╝
 * ███████╗█████╗  ██████╔╝██║   ██║██╔████╔██║███████║███████╗█████╔╝
 * ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██║╚██╔╝██║██╔══██║╚════██║██╔═██╗
 * ███████║███████╗██║  ██║ ╚████╔╝ ██║ ╚═╝ ██║██║  ██║███████║██║  ██╗
 * ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
 */

namespace Simply_Static\Backup\ThirdParty\servmask\extensions;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Archive_Exception extends \Exception {}
class Backups_Exception extends \Exception {}
class Backup_Exception extends \Exception {}
class Http_Exception extends \Exception {}
class Import_Exception extends \Exception {}
class Import_Retry_Exception extends \Exception {}
class Not_Accessible_Exception extends \Exception {}
class Not_Seekable_Exception extends \Exception {}
class Not_Tellable_Exception extends \Exception {}
class Not_Readable_Exception extends \Exception {}
class Not_Writable_Exception extends \Exception {}
class Not_Truncatable_Exception extends \Exception {}
class Not_Closable_Exception extends \Exception {}
class Not_Found_Exception extends \Exception {}
class Not_Directory_Exception extends \Exception {}
class Not_Valid_Secret_Key_Exception extends \Exception {}
class Quota_Exceeded_Exception extends \Exception {}
class Storage_Exception extends \Exception {}
class Compatibility_Exception extends \Exception {}
class Feedback_Exception extends \Exception {}
class Database_Exception extends \Exception {}
class Not_Encryptable_Exception extends \Exception {}
class Not_Decryptable_Exception extends \Exception {}
