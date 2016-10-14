<?php

namespace Reen\VagrantRepo;

class Box
{
    /**
     * @var string
     */
    private $name;
    /**
     * @var string
     */
    private $description;
    /**
     * @var array
     */
    private $versions;
    /**
     * @var array
     */
    private $versionPaths;

    /**
     * @param string $name
     * @param string $description
     * @param array  $versions
     * @param array  $versionPaths
     */
    public function __construct($name, $description, $versions, $versionPaths)
    {
        $this->name = $name;
        $this->description = $description;
        $this->versions = $versions;
        $this->versionPaths = $versionPaths;
    }

    /**
     * @return array
     */
    public function describe()
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->versions,
        ];
    }

    /**
     * @param string $version
     *
     * @return bool
     */
    public function hasVersion($version)
    {
        return array_key_exists($version, $this->versionPaths);
    }

    /**
     * @param string $version
     *
     * @return string
     */
    public function path($version)
    {
        if (!array_key_exists($version, $this->versionPaths)) {
            throw new \RuntimeException('Version does not exist.');
        }

        return $this->versionPaths[$version];
    }
}
