<?php
/*
 * Copyright (c) 2010, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * @ingroup database
 */
class sly_DB_PDO_SQLBuilder_MYSQL extends sly_DB_PDO_SQLBuilder
{
	public function build_limit($sql, $offset = 0, $limit = -1)
	{
		$offset = abs((int) $offset);
		$limit  = (int) $limit;
		$limit  = $limit < 0 ? '18446744073709551615' : $limit;

		return "$sql LIMIT $offset, $limit";
	}

	public function build_list_tables()
	{
		return 'SHOW TABLES';
	}
}