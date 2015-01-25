<?php namespace Foil\Extensions;

use Foil\Contracts\ExtensionInterface;
use Foil\Contracts\TemplateAwareInterface as TemplateAware;
use Foil\Contracts\APIAwareInterface as APIAware;
use Foil\Traits;
use igorw;
use Closure;

/**
 * Extension that provides very short functions names to be used in template files to run common
 * tasks, mainly get, escape and filter variables.
 *
 * @author Giuseppe Mazzapica <giuseppe.mazzapica@gmail.com>
 * @package foil\foil
 * @license http://opensource.org/licenses/MIT MIT
 */
class Helpers implements ExtensionInterface, TemplateAware, APIAware
{
    use Traits\TemplateAwareTrait,
        Traits\APIAwareTrait;

    private $autoescape;

    public function __construct(array $options)
    {
        $this->autoescape = ! isset($options['autoescape']) || ! empty($options['autoescape']);
    }

    public function setup(array $args = [])
    {
        return $args;
    }

    public function provideFilters()
    {
        return [
            'e'      => 'Foil\entities',
            'escape' => 'Foil\entities'
        ];
    }

    public function provideFunctions()
    {
        return [
            'v'      => [$this, 'variable'],
            'e'      => [$this, 'escape'],
            'escape' => 'Foil\entities',
            'ee'     => 'Foil\entities',
            'd'      => [$this, 'decode'],
            'decode' => 'Foil\decode',
            'dd'     => 'Foil\decode',
            'in'     => [$this, 'getIn'],
            'raw'    => [$this, 'raw'],
            'a'      => [$this, 'asArray'],
            'araw'   => [$this, 'asArrayRaw'],
            'f'      => [$this, 'filter'],
            'ifnot'  => [$this, 'ifNot'],
        ];
    }

    /**
     * Return a value from template context, optionally set a default and filter.
     * If autoescape is set to true strings are escaped for html entities
     *
     * @param  string       $var     Variable name
     * @param  mixed        $default Default
     * @param  string|array $filter  Array or pipe-separated list of filters
     * @return mixed
     */
    public function variable($var, $default = '', $filter = null)
    {
        return $this->autoescape ?
            $this->escape($var, $default, $filter) :
            $this->raw($var, $default, $filter);
    }

    /**
     * Return a value from template context, optionally set a default and filter.
     * Strings are escaped for html entities.
     *
     * @param  string       $var     Variable name
     * @param  mixed        $default Default
     * @param  string|array $filter  Array or pipe-separated list of filters
     * @return mixed
     */
    public function escape($var, $default = '', $filter = null)
    {
        return $this->api()->entities($this->raw($var, $default, $filter));
    }

    /**
     * Get and value from template context, optionally set a default and filter.
     * Strings are decoded from html entities.
     *
     * @param  string       $var     Variable name
     * @param  mixed        $default Default
     * @param  string|array $filter  Array or pipe-separated list of filters
     * @return mixed
     */
    public function decode($var, $default = '', $filter = null)
    {
        return $this->api()->decode($this->raw($var, $default, $filter));
    }

    /**
     * Return a value from template context, optionally set a default and filter.
     *
     * @param  string       $var     Variable name
     * @param  mixed        $default Default
     * @param  string|array $filter  Array or pipe-separated list of filters
     * @return mixed
     */
    public function raw($var, $default = '', $filter = null)
    {
        $data = $this->get($var);
        if (is_null($data['data'])) {
            $data['data'] = $this->returnDefault($default);
        }
        if (is_string($filter)) {
            $filter = explode('|', $filter);
        }
        $filters = array_merge($data['filters'], (array) $filter);
        if (empty($filters)) {
            return $data['data'];
        }

        return $this->template()->filter($filters, $data['data']);
    }

    /**
     * Return a value from template context, optionally set a default and filter.
     * If autoescape is set to true strings are escaped for html entities.
     * result is casted to array.
     *
     * @param  string       $var       Variable name
     * @param  mixed        $default   Default
     * @param  string|array $filter    Array or pipe-separed list of filters
     * @param  boolean      $force_raw Should use raw variable?
     * @return mixed
     */
    public function asArray($var, $default = [], $filter = null, $force_raw = false)
    {
        $raw = $this->raw($var, (array) $default, $filter);

        return $this->api()->arraize($raw, ($this->autoescape && ! $force_raw));
    }

    /**
     * Return a value from template context, optionally set a default and filter.
     * If autoescape is set to true strings are escaped for html entities.
     * result is casted to array.
     *
     * @param  string       $var     Variable name
     * @param  mixed        $default Default
     * @param  string|array $filter  Array or pipe-separated list of filters
     * @return mixed
     */
    public function asArrayRaw($var, $default = [], $filter = null)
    {
        return $this->asArray($var, $default, $filter, true);
    }

    /**
     * If a value from template context, isn't set or is empty return whatever passed as default.
     *
     * @param  string       $var     Variable name
     * @param  mixed        $default Default
     * @param  string|array $filter  Array or pipe-separed list of filters
     * @return mixed
     */
    public function ifNot($var, $default = '', $filter = null)
    {
        $raw = $this->raw($var, false, $filter);

        return empty($raw) ? $this->returnDefault($default) : '';
    }

    /**
     * Return a value from template context after filter it, optionally set a default.
     * If autoescape is set to true strings are escaped for html entities.
     *
     * @param  string|array $filters     Array or pipe-separated list of filters
     * @param  string       $var         Variable name
     * @param  array|void   $filter_args Array or additional arguments for filters
     * @param  mixed        $default     Default
     * @return mixed
     */
    public function filter($filters, $var, array $filter_args = null, $default = '')
    {
        return $this->template()->filter($filters, $this->variable($var, $default), $filter_args);
    }

    /**
     * Allow dot syntax access to any data
     *
     * @param  mixed        $data
     * @param  string|array $where
     * @return type
     */
    public function getIn($data, $where)
    {
        if (is_object($data)) {
            $data = $this->api()->arraize($data, $this->autoescape);
        } elseif (! is_array($data)) {
            return $this->autoescape ? $this->api()->entities($data) : $data;
        }

        return igorw\get_in($data, is_string($where) ? explode('.', $where) : (array) $where);
    }

    /**
     * Get a raw variable from template context.
     * Associative arrays can be accessed using dot notation.
     * Variable name can contain one or more filters using the notation:
     * "grandparent.parent.child|filter1|filter2"
     *
     * @param  string $var
     * @return mixed
     * @access private
     */
    private function get($var)
    {
        $data = $this->template()->data();
        if (empty($data)) {
            return [ 'data' => null, 'filters' => []];
        }
        $filters = explode('|', $var);
        $where = explode('.', array_shift($filters));

        return [ 'data' => igorw\get_in($data, $where), 'filters' => $filters];
    }

    private function returnDefault($default)
    {
        if ($default instanceof Closure) {
            ob_start();
            $return = call_user_func($default);
            $buffer = ob_get_clean();
            $default = empty($return) ? $buffer : $return;
        }

        return $default;
    }
}
