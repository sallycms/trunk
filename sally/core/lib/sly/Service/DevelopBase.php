<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

/**
 * Base service-class for development files such as templates, modules,
 * actions. This class holds general methods, that are used in all
 * implementations.
 *
 * @ingroup service
 */
abstract class sly_Service_DevelopBase {
	private $data;             ///< array
	private $lastRefreshTime;  ///< int

	protected $conditionEvaluators = array(); ///< array
	protected $config;                        ///< sly_Configuration
	protected $dispatcher;                    ///< sly_Event_IDispatcher

	/**
	 * Constructor
	 *
	 * @param sly_Configuration     $config
	 * @param sly_Event_IDispatcher $dispatcher
	 */
	public function __construct(sly_Configuration $config, sly_Event_IDispatcher $dispatcher) {
		$this->config     = $config;
		$this->dispatcher = $dispatcher;
	}

	/**
	 * Get a list of files for this type of items
	 *
	 * @param  boolean  $absolute  true, if the paths should be absolute (default: true)
	 * @return array               Array of files
	 */
	public function getFiles($absolute = true) {
		$dir    = $this->getFolder();
		$dirObj = new sly_Util_Directory($dir);
		$files  = $dirObj->listRecursive(false, true);
		$retval = array();

		foreach ($files as $filename) {
			if ($this->isFileValid($filename)) {
				$file = $absolute ? $filename : $this->getRelName($filename);
				$retval[] = $file;
			}
		}

		natcasesort($retval);
		return $retval;
	}

	/**
	 * Gets the folder where the development files can be found
	 *
	 * @return string  folder path
	 */
	public function getFolder() {
		$dir = sly_Util_Directory::join(SLY_DEVELOPFOLDER, $this->getClassIdentifier());
		return sly_Util_Directory::create($dir, null, true);
	}

	/**
	 * Performs a refresh.
	 *
	 * Reads the parameters from the development files if changed.
	 * If force is enabled the refresh ist done for all files.
	 *
	 * @param boolean  $force  true enables force. default: false
	 */
	public function refresh($force = false) {
		$refresh = $force || $this->needsRefresh();
		if (!$refresh) return true;

		$files    = $this->getFiles();
		$newData  = array();
		$oldData  = $this->getData();
		$modified = false;
		$dir      = $this->getFolder();

		foreach ($files as $file) {
			$basename = $this->getRelName($file);
			$mtime    = filemtime($file);
			$type     = $this->getFileType($basename);
			$arrayKey = $this->encodeFilenameForArray($basename);

			// Wenn sich die Datei nicht geändert hat, können wir die bekannten
			// Daten einfach 1:1 übernehmen.

			$known = $this->find('filename', $basename, $this->getFileType($basename));

			if ($known && $oldData[$known][$type][$arrayKey]['mtime'] == $mtime) {
				$newData[$known][$type][$arrayKey] = $oldData[$known][$type][$arrayKey];
				continue;
			}

			$parser = new sly_Util_ParamParser($file);
			$data   = $parser->get();

			if (empty($data) && $known) {
				$modified = true;
			}

			$name = $parser->get('name', null);

			if (!$this->areParamsValid($name, $data, $newData, $basename, $type)) {
				continue;
			}

			$newData[$name][$type][$arrayKey] = $this->buildData($basename, $mtime, $parser->get());

			$modified = true;
		}

		// Wir müssen die Daten erst aus der Konfiguration entfernen, falls sich
		// der Datentyp geändert hat. Ansonsten wird sich sly_Configuration z. B.
		// weigern, aus einem Skalar ein Array zu machen.
		if ($modified || count($newData) != count($oldData)) {
			$this->config->remove($this->getClassIdentifier());
			$this->setData($newData);
			$this->resetRefreshTime();
			$this->dispatcher->notify('SLY_DEVELOP_REFRESHED');
		}
	}

	/**
	 * Checks, if a refresh of the files is necessary
	 *
	 * @return boolean  true, when refresh is necessary
	 */
	protected function needsRefresh() {
		$refresh = $this->getLastRefreshTime();
		if ($refresh == 0) return true;

		$files = $this->getFiles();
		$known = $this->getKnownFiles();
		$bases = array();

		foreach ($files as $file) {
			$bases[] = $this->getRelName($file);
		}

		return
			/* files?         */ count($files) > 0 &&
			/* new data?      */ (max(array_map('filemtime', $files)) > $refresh ||
			/* new files?     */ count(array_diff($bases, $known)) > 0 ||
			/* deleted files? */ count(array_diff($known, $bases)) > 0);
	}

	/**
	 * Check if the parameters of an development file are valid.
	 *
	 * Implementors may overload this method to make sure the
	 * development files have all necessary data set. If a file
	 * does not match the requirements a warning is thrown.
	 * (e.g. when required parameters are missing or when the
	 * syntax contains errors)
	 *
	 * @param  string $name      Name of the develoment file
	 * @param  array  $params    Array with parameters parsed from the file
	 * @param  array  $newData   Array with the parameters of all previous iterated files
	 * @param  string $filename
	 * @param  string $type
	 * @return boolean           true, when the parameters are valid. false for invalid.
	 */
	protected function areParamsValid($name, $params, $newData, $filename, $type) {
		$result = true;

		if ($name === null) {
			trigger_error(t('file_has_no_internal_name', $filename), E_USER_WARNING);
			$result = false;
		}

		/*
		if (isset($newData[$name][$type])) {
			trigger_error($filename.' has no unique name. (type: '.$type.')', E_USER_WARNING);
			$result = false;
		}
		*/

		if (preg_match('#[^a-z0-9_.-]#i', $name)) {
			trigger_error(t('name_contains_invalid_characters', $name), E_USER_WARNING);
			$result = false;
		}

		return $result;
	}

	/**
	 * Get the data array
	 *
	 * @return array  The parameters of all items as an associative array
	 */
	protected function getData() {
		if (!isset($this->data)) {
			$this->data = $this->config->get($this->getClassIdentifier().'/data', array());
		}

		return $this->data;
	}

	/**
	 * @param array $data
	 */
	protected function setData($data) {
		$this->config->set($this->getClassIdentifier().'/data', $data);
		$this->data = $data;
	}

	/**
	 * Find a development file by attribute
	 *
	 * @param  string $attribute  attribute name
	 * @param  string $value      attribute value
	 * @param  string $type       filetype if necessary (default: null)
	 * @return string             element name or false if not found
	 */
	public function find($attribute, $value, $type = null) {
		$data = $this->getData();
		$type = $type === null ? $this->getFileType() : $type;

		foreach (array_keys($data) as $name) {
			if (!isset($data[$name][$type])) continue;

			foreach ($data[$name][$type] as $file) {
				if (isset($file[$attribute]) && $file[$attribute] == $value) return $name;
			}
		}

		return false;
	}

	/**
	 * Checks, when the data was refreshed last time
	 *
	 * @return int  refresh timestamp
	 */
	protected function getLastRefreshTime() {
		if (!isset($this->lastRefreshTime)) {
			$this->lastRefreshTime = $this->config->get($this->getClassIdentifier().'/last_refresh', 0);
		}

		return $this->lastRefreshTime;
	}

	/**
	 * Reset the refresh time
	 *
	 * @param int $time  the new timestamp. Leave this null for the current timestamp time();
	 */
	protected function resetRefreshTime($time = null) {
		if ($time === null) $time = time();
		$this->config->set($this->getClassIdentifier().'/last_refresh', $time);
		$this->lastRefreshTime = $time;
	}

	/**
	 * Checks, if an item exists
	 *
	 * @param  string $name  the name of the item
	 * @return boolean       true, if the item exists
	 */
	public function exists($name) {
		$data = $this->getData();
		return isset($data[$name]);
	}

	/**
	 * Get an array with all known files.
	 *
	 * @return array  array with known files
	 */
	public function getKnownFiles() {
		$known = array();
		$data  = $this->getData();

		foreach ($data as $types) {
			foreach ($types as $files) {
				foreach ($files as $file) {
					if (!empty($file['filename'])) $known[] = $file['filename'];
				}
			}
		}

		natsort($known);
		return $known;
	}

	/**
	 * Get a parameter of a resource
	 *
	 * The parameters of the resource is directly returned. The key 'params'
	 * returns an array with all user defined parameters. User parameters may
	 * also be fetched directly by giving the name of the parameter.
	 *
	 * @throws sly_Exception      when the resource with the given name is not available
	 * @param  string  $name      name of the item
	 * @param  string  $key       key of the desired parameter. null gets all. (default: null)
	 * @param  string  $default   default value, if the desired parameter is not set (default: null)
	 * @param  string  $type      filetype if necessary (default: null)
	 * @param  string  $filename  a special filename to get a parameter from
	 * @return mixed              array with all user defined parameters or string with the desired parameter
	 */
	public function get($name, $key = null, $default = null, $type = null, $filename = null) {
		if ($key == 'name') return $name;

		$data = $this->getData();

		if (!isset($data[$name])) {
			throw new sly_Exception(t('develop_file_not_found', $name));
		}

		if ($type !== null && !isset($data[$name][$type])) {
			return $default;
		}

		// get default type if necessary
		if ($type === null) {
			foreach ($this->getFileTypes() as $fileType) {
				if (isset($data[$name][$fileType])) {
					$type = $fileType;
					break;
				}
			}
		}

		if ($filename !== null) {
			$filename = $this->encodeFilenameForArray($filename);
		}

		// return all data?
		if ($filename !== null && isset($data[$name][$type][$filename])) {
			$result = $data[$name][$type][$filename];
		}
		else {
			$result = current($data[$name][$type]);
		}

		if ($key === null) {
			return $result;
		}

		// check for standard params first, then for custom params.
		return (isset($result[$key]) ? $result[$key] : (isset($result['params'][$key]) ? $result['params'][$key] : $default));
	}

	/**
	 * Gets the content of a file
	 *
	 * @throws sly_Exception     When file does not exist.
	 * @param  string $filename  Type if necessary (default: null)
	 * @return string            Content of the file
	 */
	public function getContent($filename) {
		$filename = sly_Util_Directory::join($this->getFolder(), $filename);
		if (!file_exists($filename)) throw new sly_Exception(t('file_not_found', $filename));
		return file_get_contents($filename);
	}

	/**
	 * Uses the registered filter functions to reduce the set of filenames
	 * by configurable conditions.
	 *
	 * @throws sly_Exception  When all files are filtered
	 * @param  string $name   Name of the item
	 * @param  string $type   realm of the item
	 * @return string         One filename of all files for name and type
	 */
	protected function filterByCondition($name, $type) {
		$data      = $this->getData();
		$filenames = array_keys($data[$name][$type]);

		// Replace the file key ('foo*bar.php') with the real path ('foo/bar.php'),
		// so that all event listeners can use the real path.
		foreach ($filenames as $idx => $key) {
			$filenames[$idx] = $this->decodeFileKey($key);
		}

		// need to check the files
		if (count($filenames) > 1) {
			// run all evaluators
			foreach ($this->conditionEvaluators as $param => $evaluator) {
				// prepare data
				$filter = array();

				foreach ($filenames as $idx => $filename) {
					$key  = $this->encodeFilenameForArray($filename);
					$info = $data[$name][$type][$key];

					if (isset($info['params'][$param])) {
						$filter[$filename] = $info['params'][$param];
					}
				}

				$result = call_user_func_array($evaluator, array($name, $filter));

				// if the result is not false or empty or something go on with the result
				if (!empty($result)) {
					$filenames = array_keys($result);
				}
				// else remove the files from the list
				else {
					$filenames = array_diff($filenames, array_keys($filter));
				}
			}

			// if all files got filtered away
			if (empty($filenames)) {
				throw new sly_Exception(t('condition_handlers_empty_result', $name));
			}

			// if multiple resources are left over
			if (count($filenames) > 1) {
				// warn the user
				if (!sly_Core::isBackend()) {
					$files = implode(', ', $filenames);
					trigger_error(t('condition_handlers_result_ambiguous', $name, $files), E_USER_WARNING);
				}

				// try to find one without without conditions
				foreach ($filenames as $filename) {
					$nocondition = true;
					$key         = $this->encodeFilenameForArray($filename);

					foreach (array_keys($this->conditionEvaluators) as $condition) {
						$nocondition = $nocondition || isset($data[$name][$type][$key]['params'][$condition]);
					}

					if ($nocondition) {
						return $filename;
					}
				}
			}
		}

		// return the first to find
		return current($filenames);
	}

	/**
	 * registeres a filter function for develop items
	 *
	 * @param string   $param      the @sly param this filter depends on
	 * @param callable $evaluator  A callable method to filter items
	 */
	public function registerConditionEvaluator($param, $evaluator) {
		$this->conditionEvaluators[$param] = $evaluator;
	}

	protected function getRelName($filename) {
		return str_replace(DIRECTORY_SEPARATOR, '/', sly_Util_Directory::getRelative($filename, $this->getFolder()));
	}

	protected function encodeFilenameForArray($filename) {
		return str_replace(array('/', '\\'), '*', $filename);
	}

	protected function decodeFileKey($filename) {
		return str_replace('*', '/', $filename);
	}

	/**
	 * Checks if a file matches the desired naming convention
	 *
	 * Implementors will be called, before the development files
	 * are read from their location to filter files, that do not match
	 * their naming convention.
	 *
	 * @param  string $filename  The Filename
	 * @return boolean           true, when the file is a valid development resource
	 */
	abstract protected function isFileValid($filename);

	/**
	 * Gets the filetype of the current file
	 *
	 * Implementors may discern between various file types (e.g. input and
	 * output files for modules). The difference is typically checked via a
	 * naming convention for the filename.
	 *
	 * Implementors must return a valid filetype for every imput. (e.g. a
	 * default filetype for a invalid input)
	 *
	 * @param  string  $filename  The filename (may be empty)
	 * @return string             The filetype for the current filename
	 */
	abstract protected function getFileType($filename = '');

	/**
	 * Gets a list with possible filetypes
	 *
	 * Implementors must return a list with their possible filetypes
	 *
	 * @return array  Array of strings with possible filetypes
	 */
	abstract public function getFileTypes();

	/**
	 * Get a unique identifier for this implementor
	 *
	 * This identifier is used for caching purposes. It should be the same
	 * as the folder name for the development resources in the /develop
	 * directory, where the files for this implementor are expected.
	 *
	 * @return string  The unique class identifier
	 */
	abstract protected function getClassIdentifier();

	/**
	 * Gets the implementor-specific data array from the parsed data from file
	 *
	 * Implementors should return an array of parameters. The default
	 * parameters that are used by the implementor should be in the first level
	 * of this array. The developer of a development resource should be able to
	 * enter own parameters. These parameters will be expected in a 'params'
	 * field.
	 * Furthermore the parameters 'name' and 'mtime' are required and 'title'
	 * is recommended.
	 *
	 * e.g.
	 *
	 * array(
	 *   'name'   => 'test',
	 *   'title'  => 'My template',
	 *   'mtime'  => 12345...,
	 *   'params' => array(
	 *     'user_param_1' => 'test',
	 *     'user_param_2' => 'test2'
	 *   )
	 * )
	 *
	 * @param  string  $filename  The filename of the current development resource
	 * @param  int     $mtime     The timestamp of the last change of this resource
	 * @param  array   $data      An associative array with all parameters, parsed from the resource
	 * @return array              The parameter structure for this specific resource
	 */
	abstract protected function buildData($filename, $mtime, $data);

}
