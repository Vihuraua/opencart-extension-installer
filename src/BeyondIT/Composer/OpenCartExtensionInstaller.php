<?php

namespace BeyondIT\Composer;

use Composer\Installer\LibraryInstaller;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Symfony\Component\Filesystem\Filesystem;
use BeyondIT\Composer\OpenCartNaivePhpInstaller;

class OpenCartExtensionInstaller extends LibraryInstaller
{
    public function getOpenCartDir()
    {
        $extra = $this->composer->getPackage()->getExtra();

        if (isset($extra['opencart-dir'])) {
            return $extra['opencart-dir'];
        }

        // OC 2.2.0.0 directory "upload" is root dir
        return 'upload';
    }

    /**
     * Get src path of module
     */
    public function getSrcDir($installPath, array $extra)
    {
        if (isset($extra['src-dir']) && is_string($extra['src-dir'])) {
            $installPath .= "/" . $extra['src-dir'];
        } else { // default
            $installPath .= "/src/upload";
        }

        return $installPath;
    }

    /**
     * @param array $extra extra array
     */
    public function copyFiles($sourceDir, $targetDir, array $extra)
    {
        $filesystem = new Filesystem();

        if (isset($extra['mappings']) && is_array($extra['mappings'])) {
            foreach($extra['mappings'] as $mapping) {
                $source = $sourceDir . "/" . $mapping;
                $target = $targetDir . "/" . $mapping;
                $filesystem->copy($source, $target, true);
            }
        }
    }

    /**
     * @param string $installPath
     * @param array $extra extra array
     */
    public function runExtensionInstaller(PackageInterface $package)
    {
        $extra = $package->getExtra();
        $installPath = $this->getInstallPath($package);
        $name = $package->getName();

        if (isset($extra['installers']) && is_array($extra['installers'])) {
            if (isset($extra['installers']['php'])) {
                $this->runPhpExtensionInstaller($installPath . "/" . $extra['installers']['php']);
            } elseif (isset($extra['installers']['xml'])) {
                $this->runXmlExtensionInstaller($name, $installPath . "/" . $extra['installers']['xml']);
            }
        }
    }

    public function runPhpExtensionInstaller($file) {
        $dir = $this->getOpenCartDir();

        chdir("./".$dir);

        $_SERVER['SERVER_PORT'] = 80;
        $_SERVER['SERVER_PROTOCOL'] = 'CLI';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        ob_start();
        include('admin/index.php');
        ob_end_clean();

        chdir('.');

        // $registry comes from admin/index.php
        OpenCartNaivePhpInstaller::$registry = $registry;

        $installer = new OpenCartNaivePhpInstaller();
        $installer->install($file);
    }

    public function runXmlExtensionInstaller($name, $src) {
        $filesystem = new Filesystem();
        $target = $this->getOpenCartDir() . "/system/storage/" . strtolower(str_replace("/","_",$name)) . ".ocmod.xml";
        $filesystem->copy($src, $target, true);
    }

    /**
     * { @inheritDoc }
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::install($repo, $package);

        $srcDir = $this->getSrcDir($this->getInstallPath($package), $package->getExtra());
        $openCartDir = $this->getOpenCartDir();

        $this->copyFiles($srcDir, $openCartDir, $package->getExtra());
        $this->runExtensionInstaller($package);
    }

    /**
     * { @inheritDoc }
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        parent::update($repo, $initial, $target);

        // TODO: update files from opencart
    }

    /**
     * { @inheritDoc }
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        parent::uninstall($repo, $package);

        // TODO: remove files from opencart

    }

}