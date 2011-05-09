<?php
/**
 * Flight: An extensible micro-framework.
 *
 * @copyright   Copyright (c) 2011, Mike Cao <mike@mikecao.com>
 * @license     http://www.opensource.org/licenses/mit-license.php
 * @version     0.1
 */
class View {
    public $path;
    public $template;

    public function __construct($path = null) {
        $this->path = $path ?: (Flight::get('flight.views.path') ?: './views');
    }

    /**
     * Renders a template.
     *
     * @param string $file Template file
     * @param array $data Template data
     */
    public function render($file, $data = null) {
        $this->template = (substr($file, -4) == '.php') ? $file : $file.'.php';

        extract($data);

        include $this->path.'/'.$this->template;
    }

    /**
     * Gets the output of a template.
     *
     * @param string $file Template file
     * @param array $data Template data
     */
    public function fetch($file, $data = null) {
        ob_start();

        $this->render($file, $data);
        $output = ob_get_contents();

        ob_end_clean();

        return $output;
    }

    /**
     * Loads and executes view helper functions.
     *
     * @param string $name Function name
     * @param array $params Function parameters
     */
    public function __call($name, $params) {
        return Flight::invokeMethod(array('Flight', $name), $params);
    }

    /**
     * Displays escaped output.
     *
     * @param string $str String to escape
     */
    public function e($str) {
        echo htmlentities($str);
    }
}
?>
