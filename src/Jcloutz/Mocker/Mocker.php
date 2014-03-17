<?php namespace Jcloutz\Mocker;

use \Faker\Factory;

class Mocker
{
    /**
     * @var array
     */
    private $overrideAttributes = array();

    /**
     * @var Illuminate\Database\Eloquent\Model
     */
    private $mockObject;

    /**
     * @param array $fields
     */

    public function mock($mockableObject)
    {
        $this->mockObject = $mockableObject;
        return $this;
    }

    public function override($overrides = array())
    {
        $this->overrideAttributes = $overrides;
        return $this;
    }

    /**
     * Builds an array of data for model fields.
     * @param array $attributes
     */
    public function executeAction($actionString)
    {
        // get action arguments
        $action = explode('|', $actionString);

        $classFunction = '';
        $fakerFunction = '';
        $fakerArgs = '';
        $data = '';

        if ($action[0] === 'call') {
            array_shift($action);
            $classFunction = array_shift($action);
            $fakerFunction = array_shift($action);
            $fakerArgs = $action;
            $data = $this->getModelFunctionData($classFunction, $fakerFunction, $fakerArgs);

        } elseif ($action[0] === 'static') {
            // use static value
            $data = $this->getStaticData($action);
        } else {

            // get faker data only
            $fakerFunction = array_shift($action);
            $fakerArgs = $action;
            $data = $this->getfakerData($fakerFunction, $fakerArgs);

        }
        return $data;
    }

    public function getModelFunctionData($classFunction, $fakerFunction = null, $fakerArgs = null)
    {
        // get faker data and pass to model function
        if ($fakerFunction !== null) {
            $fakerData = $this->getFakerData($fakerFunction, $fakerArgs);
            return call_user_func(array($this->mockObject, $classFunction), $fakerData);
        } else {
            return call_user_func(array($this->mockObject, $classFunction));
        }
    }

    public function getStaticData($action)
    {
        return $action[1];
    }


    public function getFakerData($function, $args = array())
    {
        $faker = Factory::create();

        if (count($args) > 0) {
            $resolvedArgs = $this->resolveArguments($args);
            $value = call_user_func_array([$faker, $function], $resolvedArgs);
        } else {
            $value = call_user_func([$faker, $function]);
        }

        return $value;
    }

    /**
     * Returns field data generated by Faker
     * @return array
     */
    public function get()
    {
        $class = get_class($this->mockObject);
        $fieldData = $class::$mockable;

        foreach ($fieldData as $fieldName => $action) {
            if (array_key_exists($fieldName, $this->overrideAttributes)) {
                $this->mockObject->$fieldName = $this->overrideAttributes[$fieldName];
            } else {
                $this->mockObject->$fieldName = $this->executeAction($action);
            }
        }

        return $this->mockObject;
    }

    private function resolveArguments($args = array())
    {
        return array_map([$this, 'convertInt'], $args);
    }

    /**
     * Used by resolveArguments as an array_map function
     * @param  string $number
     * @return mixed
     */
    private function convertInt($number)
    {
        if (is_numeric($number)) {
            $value = intval($number);
        } else {
            $value = $number;
        }

        return $value;
    }
}