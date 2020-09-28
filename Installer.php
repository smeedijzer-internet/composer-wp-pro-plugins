<?php
/**
 * Composer Installer for Pro WordPress Plugins.
 *
 * @package Junaidbhura\Composer\WPProPlugins
 */

namespace Junaidbhura\Composer\WPProPlugins;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Installer\PackageEvent;
use Composer\Installer\PackageEvents;
use Composer\Package\PackageInterface;
use Composer\Plugin\PluginEvents;
use Composer\Plugin\PreFileDownloadEvent;
use Dotenv\Dotenv;

/**
 * Custom Installer Class.
 */
class Installer implements PluginInterface, EventSubscriberInterface {

	protected $composer;
	protected $io;

	/**
	 * Activate plugin.
	 *
	 * @param Composer    $composer
	 * @param IOInterface $io
	 */
	public function activate( Composer $composer, IOInterface $io ) {
		$this->composer = $composer;
		$this->io       = $io;

		if ( file_exists( getcwd() . DIRECTORY_SEPARATOR . '.env' ) ) {
			$dotenv = Dotenv::createImmutable( getcwd() );
			$dotenv->load();
		}
	}

	/**
	 * Deactivate plugin.
	 *
	 * @param Composer    $composer
	 * @param IOInterface $io
	 */
	public function deactivate( Composer $composer, IOInterface $io ) {
		$this->composer = null;
		$this->io       = null;
	}

	/**
	 * Uninstall plugin.
	 *
	 * @param Composer    $composer
	 * @param IOInterface $io
	 */
	public function uninstall( Composer $composer, IOInterface $io ) {
		// no need to uninstall anything
	}

	/**
	 * Set subscribed events.
	 *
	 * @return array
	 */
	public static function getSubscribedEvents() {
		return array(
			PackageEvents::PRE_PACKAGE_INSTALL => 'onPrePackageInstall',
			PackageEvents::PRE_PACKAGE_UPDATE  => 'onPrePackageUpdate',
			PluginEvents::PRE_FILE_DOWNLOAD    => array( 'onPreFileDownload', -1 ),
		);
	}

	/**
	 * Prepare the dist URL before the package is installed.
	 *
	 * @param PackageEvent $event
	 */
	public function onPrePackageInstall( PackageEvent $event ) {
		$package = $event->getOperation()->getPackage();

		if ( $this->isPackageSupported( $package ) ) {
			$this->updatePackageDistUrl( $package );
		}
	}

	/**
	 * Prepare the dist URL before the package is updated.
	 *
	 * @param PackageEvent $event
	 */
	public function onPrePackageUpdate( PackageEvent $event ) {
		$package = $event->getOperation()->getTargetPackage();

		if ( $this->isPackageSupported( $package ) ) {
			$this->updatePackageDistUrl( $package );
		}
	}

	/**
	 * Prepare the download URL.
	 *
	 * In Composer v2, all packages get downloaded first,
	 * then prepared, then installed/updated/uninstalled.
	 *
	 * @param PreFileDownloadEvent $event
	 */
	public function onPreFileDownload( PreFileDownloadEvent $event ) {
		if ($event->getType() !== 'package') {
			return;
		}

		$package = $event->getContext();

		if ( ! ( $package instanceof PackageInterface ) ) {
			return;
		}

		$processed_url = $event->getProcessedUrl();
		$filtered_url  = $this->filterProcessedUrl( $processed_url, $package );

		if ( $filtered_url !== $processed_url ) {
			if ( version_compare( PluginInterface::PLUGIN_API_VERSION, '2.0.0', '>=' ) ) {
				$this->updatePackageDistUrl( $package );

				$event->setProcessedUrl( $filtered_url );
			} else {
				$originalRemoteFilesystem = $event->getRemoteFilesystem();
				$customRemoteFilesystem   = new RemoteFilesystem(
					$filtered_url,
					$this->io,
					$this->composer->getConfig(),
					$originalRemoteFilesystem->getOptions(),
					$originalRemoteFilesystem->isTlsDisabled()
				);
				$event->setRemoteFilesystem( $customRemoteFilesystem );
			}
		}
	}

	/**
	 * Determine if the package is a supported WP plugin.
	 *
	 * @param PackageInterface $package
	 *
	 * @return bool
	 */
	public function isPackageSupported( PackageInterface $package ) {
		$package_name = $package->getName();

		if ( 'junaidbhura/advanced-custom-fields-pro' === $package_name ) {
			return true;
		}

		if ( 'junaidbhura/polylang-pro' === $package_name ) {
			return true;
		}

		if ( 'junaidbhura/wp-all-import-pro' === $package_name ) {
			return true;
		}

		if ( 'junaidbhura/wp-all-export-pro' === $package_name ) {
			return true;
		}

		if ( 0 === strpos( $package_name, 'junaidbhura/gravityforms' ) ) {
			return true;
		}

		if ( 0 === strpos( $package_name, 'junaidbhura/wpai-' ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Update the dist URL on the given package.
	 *
	 * @param PackageInterface $package
	 */
	public function updatePackageDistUrl( PackageInterface $package ) {
		$dist_url     = $package->getDistUrl();
		$filtered_url = $this->filterDistUrl( $dist_url, $package );

		if ( $filtered_url !== $dist_url ) {
			$package->setDistUrl( $filtered_url );
		}
	}

	/**
	 * Filter the dist URL for a given package.
	 *
	 * The filtered dist URL is stored inside `composer.lock` and is used
	 * to generate the cache key for the requested package version.
	 *
	 * @param string|null      $url
	 * @param PackageInterface $package
	 *
	 * @return string|null The filtered dist URL.
	 */
	protected function filterDistUrl( $url, PackageInterface $package ) {
		if ( ! $this->isPackageSupported( $package ) ) {
			return $url;
		}

		$package_key = sha1( $package->getUniqueName() );
		if ( false === strpos( $url, $package_key ) ) {
			$url .= '#' . $package_identifier;
		}

		return $url;
	}

	/**
	 * Filter the processed URL before downloading.
	 *
	 * The filtered processed URL is not stored inside `composer.lock`
	 * and altered to reflect the real download URL for the WP plugin.
	 *
	 * @param string|null      $url
	 * @param PackageInterface $package
	 *
	 * @return string|null The filtered processed URL.
	 */
	protected function filterProcessedUrl( $url, PackageInterface $package ) {
		$plugin       = null;
		$package_name = $package->getName();

		switch ( $package_name ) {
			case 'junaidbhura/advanced-custom-fields-pro':
				$plugin = new Plugins\AcfPro( $package->getPrettyVersion() );
				break;

			case 'junaidbhura/polylang-pro':
				$plugin = new Plugins\PolylangPro( $package->getPrettyVersion() );
				break;

			case 'junaidbhura/wp-all-import-pro':
			case 'junaidbhura/wp-all-export-pro':
				$plugin = new Plugins\WpAiPro( $package->getPrettyVersion(), str_replace( 'junaidbhura/', '', $package_name ) );
				break;

			default:
				if ( 0 === strpos( $package_name, 'junaidbhura/gravityforms' ) ) {
					$plugin = new Plugins\GravityForms( $package->getPrettyVersion(), str_replace( 'junaidbhura/', '', $package_name ) );
				} elseif ( 0 === strpos( $package_name, 'junaidbhura/wpai-' ) ) {
					$plugin = new Plugins\WpAiPro( $package->getPrettyVersion(), str_replace( 'junaidbhura/', '', $package_name ) );
				}
		}

		if ( $plugin ) {
			return $plugin->getDownloadUrl();
		}

		return $url;
	}

}
