<?php
class AclPlusComponent extends Object
{

    public $components = array(
        'Session'
    );

    public $sessionKey = 'AclPlus';

    public $requester = array('model' => 'User', 'id' => 0);

    public $validationTypes = array();

    public $aroModel = 'Aro';
    public $acoModel = 'Aco';
    public $joinModel = 'ArosAco';

    public $controllerAco = array('controller' => null, 'action' => null);
    public $modelAco = array('model' => 'models', 'id' => null);

    private $__aros = array();
    private $__acos = array();

    public function initialize(&$controller, $settings = array())
    {

        $this->_set($settings);

        if (empty($this->controllerAco['controller'])) {
            $this->controllerAco['controller'] = $controller->name;
        }

        if (empty($this->controllerAco['action'])) {
            $this->controllerAco['action'] = $controller->params['action'];
        }

        $this->__getModel($this->acoModel, 'Aco');
        $this->__getModel($this->aroModel, 'Aro');
        $this->__getModel($this->joinModel, 'ArosAco');

    }

    public function check($model = 'User', $id = 0)
    {

        //check we have a requester to work with
        if ($model !== 'User') {
            $this->requester['model'] = $model;
        }

        if ($id) {
            $this->requester['id'] = $id;
        }

        if (empty($this->requester['model']) || empty($this->requester['id'])) {
            //trigger_error() may be more appropriate
            return false;
        }

        //get all AROs relating to the passed ARO model and ID and save to session as a list of ids
        $aros = $this->get('aros');

        //get a list of Ids that match the current request
        $acos = $this->__buildAcos();

        //Find the links
        return $this->__joinCheck($aros, $acos);

    }

    private function __joinCheck($aros = array(), $acos = array())
    {

        if (!$acos || !$aros) {
            return false;
        }
        $model = $this->__getModel('ArosAco');

        foreach ($acos as $aco) {
            $find = $model->find('count', array(
                    'conditions' => array(
                        $this->joinModel . '.aro_id' => $aros,
                        $this->joinModel . '.aco_id' => $aco
                    )
                )
            );
            if ($find) {
                return true;
            }
        }
        
    }

    public function get($type = 'aros')
    {

        if ($session = $this->Session->read($this->sessionKey . '.' . $type)) {
            return $session;
        }

        return $this->rebuild($type);

    }

    public function rebuild($type)
    {

        $method = '__build' . Inflector::camelize($type);
        $result = $this->$method();

        $this->Session->write(
            $this->sessionKey . '.' . $type,
            $result
        );

        return $result;

    }

    //Allow basepath to be set somewhere
    private function __getActionPath($basePath = 'controllers', $return = 'array')
    {

        $path = implode('/', Set::merge((array) $basePath, $this->controllerAco));

        if ($return === 'array') {
            return array_reverse(explode('/', $path));
        }
        return  $path;

    }

    private function __buildAcos($id = null, $alias = null)
    {

        $paths = $this->__getActionPath();
        $model = $this->__getModel('Aco');
        //action maps DONT FORGET ABOUT ACTION MAPS

        if (!is_null($id) && !is_null($alias)) {
            $found = $model->find('first', array(
                'conditions' => array(
                    $this->acoModel . '.id' => $id
                ),
                'fields' => array(
                    $this->acoModel . '.id',
                    $this->acoModel . '.parent_id',
                    $this->acoModel . '.alias'
                ),
                'recursive' => -1
            ));

            if ($found[$this->acoModel]['alias'] == $alias) {
                return $found;
            }
            return null;
        }

        $build = array();
        $child_alias = array_shift($paths);

        foreach ($paths as $path) {
            $parent_alais = $path;
            $children = $model->find('all', array(
                'conditions' => array(
                    $this->acoModel . '.alias' => $child_alias
                ),
                'fields' => array(
                    $this->acoModel . '.id',
                    $this->acoModel . '.parent_id'
                ),
                'recursive' => -1
            ));

            foreach ($children as $child) {
                extract($child[$this->acoModel]);
                if ($parent_id) {
                    $parent = $this->__buildAcos($parent_id, $parent_alais);
                    if ($parent) {
                        if (empty($build)) {
                            //here is where we get the action map
                            $build[] = $child[$this->acoModel]['id'];
                        }
                        $build[] = $parent[$this->acoModel]['id'];
                    }
                }
            }
            $child_alias = $parent_alais;
        }

        return $build;

    }

    private function __buildAros($id = null)
    {

        if (is_null($id)) {
            $conditions = array(
                'conditions' => array(
                    $this->aroModel . '.model' => $this->requester['model'],
                    $this->aroModel . '.foreign_key' => $this->requester['id']
                )
            );
        } else {
            $conditions = array(
                'conditions' => array(
                    $this->aroModel . '.id' => $id
                )
            );
        }

        $options = array(
            'fields' => array(
                $this->aroModel . '.id',
                $this->aroModel . '.parent_id'
            ),
            'recursive' => -1
        );

        $options = Set::merge($options, $conditions);
        $result = $this->__getModel('Aro')->find('first', $options);

        if ($result) {
            extract($result[$this->aroModel]);
        }

        $ids = array($id);
        if (!empty($parent_id)) {
            $ids[] = reset($this->__buildAros($parent_id));
        }

        return $ids;

    }

    public function allow($aro, $aco, $perms)
    {

    }

    public function deny($aro, $aco, $perms)
    {

    }

    private function __getModel($name, $alias = null)
    {

        if (empty($alias)) {
            $alias = $name;
        }

        if (empty($this->__aclModels[$alias])) {
            $this->__aclModels[$alias] = ClassRegistry::init(array('class' => $name, 'alias' => $alias));
        }

        return $this->__aclModels[$alias];

    }

}
?>