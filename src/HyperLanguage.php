<?php


/**
 * Abstract base class of all Hyperlight language definitions.
 *
 * In order to define a new language definition, this class is inherited.
 * The only function that needs to be overridden is the constructor. Helper
 * functions from the base class can then be called to construct the grammar
 * and store additional information.
 * The name of the subclass must be of the schema <var>{Lang}Language</var>,
 * where <var>{Lang}</var> is a short, unique name for the language starting
 * with a capital letter and continuing in lower case. For example,
 * <var>PhpLanguage</var> is a valid name. The language definition must
 * reside in a file located at <var>languages/{lang}.php</var>. Here,
 * <var>{lang}</var> is the all-lowercase spelling of the name, e.g.
 * <var>languages/php.php</var>.
 *
 */
abstract class HyperLanguage {
    private $_states = array();
    private $_rules = array();
    private $_mappings = array();
    private $_info = array();
    private $_extensions = array();
    private $_caseInsensitive = false;
    private $_postProcessors = array();

    private static $_languageCache = array();
    private static $_compiledLanguageCache = array();
    private static $_filetypes;

    /**
     * Indices for information.
     */

    const NAME = 1;
    const VERSION = 2;
    const AUTHOR = 10;
    const WEBSITE = 5;
    const EMAIL = 6;

    /**
     * Retrieves a language definition name based on a file extension.
     *
     * Uses the contents of the <var>languages/filetypes</var> file to
     * guess the language definition name from a file name extension.
     * This file has to be generated using the
     * <var>collect-filetypes.php</var> script every time the language
     * definitions have been changed.
     *
     * @param string $ext the file name extension.
     * @return string The language definition name or <var>NULL</var>.
     */
    public static function nameFromExt($ext) {
        if (self::$_filetypes === null) {
            $ft_content = file('languages/filetypes', 1);

            foreach ($ft_content as $line) {
                list ($name, $extensions) = explode(':', trim($line));
                $extensions = explode(',', $extensions);
                // Inverse lookup.
                foreach ($extensions as $extension)
                    $ft_data[$extension] = $name;
            }
            self::$_filetypes = $ft_data;
        }
        $ext = strtolower($ext);
        return
            array_key_exists($ext, self::$_filetypes) ?
            self::$_filetypes[strtolower($ext)] : null;
    }

    public static function compile(HyperLanguage $lang) {
        $id = $lang->id();
        if (!isset(self::$_compiledLanguageCache[$id]))
            self::$_compiledLanguageCache[$id] = $lang->makeCompiledLanguage();
        return self::$_compiledLanguageCache[$id];
    }

    public static function compileFromName($lang) {
        return self::compile(self::fromName($lang));
    }

    protected static function exists($lang) {
        return isset(self::$_languageCache[$lang]) or
               file_exists("languages/$lang.php");
    }

    protected static function fromName($lang) {
        if (!isset(self::$_languageCache[$lang])) {
            require_once("languages/$lang.php");
            $klass = ucfirst("{$lang}Language");
            self::$_languageCache[$lang] = new $klass();
        }
        return self::$_languageCache[$lang];
    }

    public function id() {
        $klass = get_class($this);
        return strtolower(substr($klass, 0, strlen($klass) - strlen('Language')));
    }

    protected function setCaseInsensitive($value) {
        $this->_caseInsensitive = $value;
    }

    protected function addStates(array $states) {
        $this->_states = self::mergeProperties($this->_states, $states);
    }

    protected function getState($key) {
        return $this->_states[$key];
    }

    protected function removeState($key) {
        unset($this->_states[$key]);
    }

    protected function addRules(array $rules) {
        $this->_rules = self::mergeProperties($this->_rules, $rules);
    }

    protected function getRule($key) {
        return $this->_rules[$key];
    }

    protected function removeRule($key) {
        unset($this->_rules[$key]);
    }

    protected function addMappings(array $mappings) {
        // TODO Implement nested mappings.
        $this->_mappings = array_merge($this->_mappings, $mappings);
    }

    protected function getMapping($key) {
        return $this->_mappings[$key];
    }

    protected function removeMapping($key) {
        unset($this->_mappings[$key]);
    }

    protected function setInfo(array $info) {
        $this->_info = $info;
    }

    protected function setExtensions(array $extensions) {
        $this->_extensions = $extensions;
    }

    protected function addPostprocessing($rule, HyperLanguage $language) {
        $this->_postProcessors[$rule] = $language;
    }

    private function makeCompiledLanguage() {
        return new HyperlightCompiledLanguage(
            $this->id(),
            $this->_info,
            $this->_extensions,
            $this->_states,
            $this->_rules,
            $this->_mappings,
            $this->_caseInsensitive,
            $this->_postProcessors
        );
    }

    private static function mergeProperties(array $old, array $new) {
        foreach ($new as $key => $value) {
            if (is_string($key)) {
                if (isset($old[$key]) and is_array($old[$key]))
                    $old[$key] = array_merge($old[$key], $new);
                else
                    $old[$key] = $value;
            }
            else
                $old[] = $value;
        }

        return $old;
    }
}
