<?php

namespace Secode\Parser;

use Nette\PhpGenerator\PhpFile;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Yaml;

class Parser
{
    private string $controllerInterfacePath = "";
    private string $dtoClassPath = "";
    private string $apiRoutesYmlPath = "";

    public function setControllerInterfacePath(string $controllerInterfacePath): void
    {
        $this->controllerInterfacePath = $controllerInterfacePath;
    }

    public function setDtoClassPath($dtoClassPath): void
    {
        $this->dtoClassPath = $dtoClassPath;
    }

    public function setApiRoutesYmlPath($apiRoutesYmlPath): void
    {
        $this->apiRoutesYmlPath = $apiRoutesYmlPath;
    }

    public function ymlToCode($filename): void
    {
        $yamlParser = new YamlParser();
        $object = $yamlParser->parseFile($filename);
        $schemas = $object['components']['schemas'];

        self::generarDTOs($schemas);
        self::generarApiRoutes($object);
    }

    public function generarDTOs($schemas): void
    {
        foreach ($schemas as $keyComponent => $component) {
            if (array_key_exists('x-api-generator-ignore', $component)) {
                continue;
            }
            $className = self::getDtoName($keyComponent);
            $file = new PhpFile;
            $file->addComment('This file is auto-generated.');
            $file->setStrictTypes();
            $nameNamespace = 'App\\Dto';
            $namespace = $file->addNamespace($nameNamespace);
            $class = $namespace->addClass($className);
            foreach ($component['properties'] as $keyProperty => $property) {
                $type = array_key_exists('type', $property) ?
                    self::getDataType($property['type']) : $nameNamespace . '\\' . self::getDtoName($keyProperty);

                $class->addProperty($keyProperty)
                    ->setPrivate()
                    ->setType($type)
                    ->setNullable();
                $class->addMethod('get' . self::toClassName($keyProperty))
                    ->setReturnType($type)
                    ->setBody('return $this->' . $keyProperty . ';');
                $class->addMethod('set' . self::toClassName($keyProperty))
                    ->setReturnType("self")
                    ->setBody(
                        '$this->' . $keyProperty . ' = $' . $keyProperty . ";\n" .
                        'return $this;')
                    ->addParameter($keyProperty)
                    ->setType($type)
                    ->setNullable();

            }
            file_put_contents("$this->dtoClassPath/$className.php", $file);
        }
    }

    public function getDtoName($keyComponent): string
    {
        return self::toClassName($keyComponent) . 'DTO';
    }

    public function toClassName(string $string): string
    {
        return strtoupper($string[0]) . substr($string, 1);
    }

    public function getDataType(string $dataType): string
    {
        return match ($dataType) {
            'integer' => 'int',
            'string' => 'string',
            'boolean' => 'bool',
            'array' => 'array'
        };
    }

    public function generarApiRoutes($object): void
    {
        $version = $object['x-ver'];

        $apiYaml = [
            'type' => 'group',
            'prefix' => [
                'type' => 'function',
                'value' => 'environmentId'
            ],
            'function' => [
                [
                    'type' => 'group',
                    'prefix' => [
                        'type' => 'string',
                        'value' => 'api'
                    ],
                    'middlewares' => ['api'],
                    'function' => [
                        [
                            'type' => 'group',
                            'prefix' => [
                                'type' => 'string',
                                'value' => $version
                            ],
                            'function' => []
                        ],
                    ]
                ],
            ]
        ];
        $functionYaml = [];
        $paths = $object['paths'];

        ksort($paths);

        $controllersInterfaces = [];
        //agrupar por perfil prefijo grupal
        $newArray = [];
        foreach ($paths as $keyPath => $path) {

            //get prefijo grupal
            $prefijoGrupal = self::getPrefijoGrupal($keyPath);
            $pathSinPrefijo = self::getPathSinPrefijo($keyPath);
            foreach ($path as $method => $endPoints) {

                $namespace = 'App\Http\Controllers\\' . $endPoints['x-controller-path'];
                $controllerName = $endPoints['tags'][0] . 'Controller';
                $operationId = $endPoints['operationId'];
                $middlewares = $endPoints['x-middlewares'] ?? [];
                $newArray[$prefijoGrupal][$pathSinPrefijo][] = [
                    'method' => $method,
                    'action' => $namespace . "\\$controllerName"."Impl@$operationId",
                    'middlewares' => $middlewares
                ];
                //obtener args

                $controllersInterfaces[$namespace][$endPoints['x-controller-path']][$controllerName][] = [
                    'method' => $operationId,
                    'args' => self::getArgumentsFromPath($keyPath)
                ];
            }
        }
        self::generarControllers($controllersInterfaces);

        foreach ($newArray as $groupKey => $endpoints) {
            $functionEndpoints = [
                [
                    'type' => 'endpoints',
                    'endpoints' => $endpoints
                ]
            ];

            $functionGroup = [
                'type' => 'group',
                'prefix' => [
                    'type' => 'string',
                    'value' => $groupKey
                ],
                'function' => $functionEndpoints
            ];
            $functionYaml[] = $functionGroup;
        }

        $apiYaml['function'][0]['function'][0]['function'] = $functionYaml;

        $ymlStr = Yaml::dump($apiYaml);
        $ymlStr = str_replace(", ", ", \n", $ymlStr);
        $ymlStr = str_replace("{ ", "{ \n", $ymlStr);
        $ymlStr = str_replace("}, ", "\n }, ", $ymlStr);
        $ymlStr = str_replace("], ", "\n ], ", $ymlStr);
        $ymlStr = str_replace("} ", "\n } ", $ymlStr);
        $ymlStr = str_replace("] ", "\n ] ", $ymlStr);
        $ymlStr = str_replace("[", "[\n", $ymlStr);


        file_put_contents($this->apiRoutesYmlPath . '/api-routes.yml', $ymlStr);
    }

    public function getPrefijoGrupal(string $string): string
    {
        return explode('/', self::deleteFirstChar($string))[0];
    }

    public function deleteFirstChar(string $string): string
    {
        return substr($string, 1);
    }

    public function getPathSinPrefijo(string $string): string
    {
        $prefijo = self::getPrefijoGrupal($string);
        $pathSinPrefijo = substr($string, strlen($prefijo) + 1);

        return $pathSinPrefijo == '' ? '/' : $pathSinPrefijo;
    }

    public function getArgumentsFromPath($string): array
    {
        $arguments = [];
        $start = "{";
        $end = "}";
        $arrayPaths = explode($start, $string);
        array_shift($arrayPaths);
        foreach ($arrayPaths as $variable) {
            $len = strpos($variable, $end);
            $arguments[] = substr($variable, 0, $len);
        }
        return $arguments;
    }

    public function generarControllers($controllersInterfaces): void
    {
        foreach ($controllersInterfaces as $nameNamespace => $directories) {
            foreach ($directories as $directory => $interfaces) {
                foreach ($interfaces as $nameInterface => $methods) {
                    $file = new PhpFile;
                    $file->addComment('This file is auto-generated.');
                    $file->setStrictTypes();
                    $namespace = $file->addNamespace($nameNamespace);
                    $interface = $namespace->addInterface($nameInterface);
                    foreach ($methods as $method) {
                        $interfaceMethod = $interface->addMethod($method['method']);
                        foreach ($method['args'] as $argument) {
                            $interfaceMethod->addParameter($argument);
                        }
                    }
                    file_put_contents("$this->controllerInterfacePath/$directory/$nameInterface.php", $file);
                }
            }
        }
    }
}

