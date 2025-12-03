<?php
namespace Catch\Plugin\Enums;

enum PluginType: string
{
    case Library = 'library';
    case Plugin = 'plugin';
    case Module = 'module';
    case Project = 'project';

    /**
     * @return bool
     */
    public function isLibrary(): bool
    {
        return $this->value === self::Library->value;
    }

    public function isPlugin(): bool
    {
        return $this->value === self::Plugin->value;
    }

    public function isModule(): bool
    {
        return $this->value === self::Module->value;
    }

    public function isProject(): bool
    {
        return $this->value === self::Project->value;
    }
}
