<?php
/**
 * Created by PhpStorm.
 * User: yf
 * Date: 2017/2/1
 * Time: 上午12:23
 */

namespace Core\Component;

use Phalcon\Di as PhalconDi;

class Di extends PhalconDi
{
    /*
     * 借以实现IOC注入
     */
    private $phalconAppDi;
    protected static $instance;
    static function getInstance(){
        if(!isset(self::$instance)){
            self::$instance = new static();
        }
        return self::$instance;
    }

    public function setPhalconAppDi(PhalconDi $phalconAppDi){
        if ($this->phalconAppDi){
            return $this->phalconAppDi;
        }
        $this->phalconAppDi = $phalconAppDi;
        return $this;
    }

    public function getPhalconAppDi(){
        return $this->phalconAppDi;
    }

    public function get($name, $parameters = null)
    {
        try{
            $di = parent::get($name, $parameters); // TODO: Change the autogenerated stub
        } catch (\Exception $e) {
            $di = false;
        }
        return $di;
    }

    public function set($name, $definition, $shared = false)
    {
        return parent::set($name, $definition, $shared); // TODO: Change the autogenerated stub
    }
}