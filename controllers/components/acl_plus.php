<?php
/**
 * AclPlus aims to be a viable alternative to those who are not comfortable with
 * CakePHPs built in AclComponent.
 *
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright 2010, Richard Vanbergen.
 * @version 0.1
 * @author Richard Vanbergen <rich97@gmail.com>
 * @license http://www.opensource.org/licenses/mit-license.php MIT
 */
class AclPlusComponent extends Object
{

/**
 * Needs session component
 */
    public $components = array(
        'Session'
    );

/**
 * Default session key
 */
    public $sessionKey = 'AclPlus';

/**
 * The current requester, used to locate the Aros and parents of those Aros
 */
    public $requester = array('model' => 'User', 'id' => 0);

/**
 * No used right now, will be used to set in which ways a developer wants to
 * validate the current ARO
 */
    public $validationTypes = array();

/**
 * Model names for Acos and Aros
 */
    public $aroModel = 'Aro';
    public $acoModel = 'Aco';
    public $joinModel = 'ArosAco';

/**
 * Holds the current controller/action combination, can be set manually or
 * loaded automagically.
 */
    public $controllerAco = array('controller' => null, 'action' => null);

/**
 * Will be used for row-level access control
 */
    public $modelAco = array('model' => 'models', 'id' => null);

/**
 * Holds a list of initalized model objects
 */
    private $__aclModels = array();

/**
 * Load settings passed to the component, and set up the models
 */
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

/**
 * Does a simple check to see if the ARO has access to and ACO (currently an action)
 */
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

        //Find the relationships
        return $this->__joinCheck($aros, $acos);

    }

/**
 * Loads a Aro/Aco set from the session, attempts to build is there is none available.
 */
    public function get($type = 'aros')
    {

        if ($session = $this->Session->read($this->sessionKey . '.' . $type)) {
            return $session;
        }

        return $this->rebuild($type);

    }

/**
 * Loads Aro/Acos into the session (this will probably change)
 */
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

/**
 * Similar to Acl::allow()
 */
    public function allow($aro, $aco, $perms)
    {

    }

/**
 * Similar to Acl::deny()
 */
    public function deny($aro, $aco, $perms)
    {

    }

/**
 * Makes a path pointing the current Aco using what is set in $this->controllerAco
 *
 * Can either return an array to itterate over or a string path like controllers/controller/action
 */
    private function __getActionPath($basePath = 'controllers', $return = 'array')
    {

        $path = implode('/', Set::merge((array) $basePath, $this->controllerAco));

        if ($return === 'array') {
            return array_reverse(explode('/', $path));
        }
        return $path;

    }

/**
 * Builds a list of Aco ids that relate to the current action. Going from most relevant to
 * least relevant for example:
 *
 * array(
 *     0 => 15, // This action
 *     1 => 14, // This controller
 *     2 => 1, // All controllers
 * );
 */
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

/**
 * Build a list of Aro ids that the main Aro is a child node of.
 * 
 * array(
 *     0 => 64, // Current logged in user
 *     1 => 1 // Administrator
 * );
 */
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

/**
 * Find where there is a link between an array of Aros and an array of Acos
 *
 * Acos are looked up in order, if the a link between the current user
 * and current action cannot be found then we look for the link between
 * the current user and the current controller, all the way up the tree
 */
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

        return false;
        
    }

/**
 * Returns a model once it has been loaded.
 */
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