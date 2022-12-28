<?php

namespace Secode\Component;

class Info
{
    private string $title;
    private string $description;
    private string $version;

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     *
     * @return Info
     */
    public function setDescription(string $description): Info
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     *
     * @return Info
     */
    public function setTitle(string $title): Info
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * @param string $version
     *
     * @return Info
     */
    public function setVersion(string $version): Info
    {
        $this->version = $version;
        return $this;
    }



}
