<?php

use Behat\Behat\Context\Context;
use Behat\MinkExtension\Context\RawMinkContext;
use Behat\Testwork\Hook\Scope\BeforeSuiteScope;

use Behat\Mink\Mink,
    Behat\Mink\Session,
    Behat\Mink\Driver\GoutteDriver,
    Behat\Mink\Driver\Goutte\Client as GoutteClient;

/**
 * Defines application features from the specific context.
 */
class FeatureContext extends RawMinkContext implements Context
{
    /**
     * Pid for the web server
     *
     * @var int
     */
    private static $pid;
    private static $mink;

    /**
     * Start up the web server
     *
     * @BeforeSuite
     */
    public static function setUp(BeforeSuiteScope $event)
    {
        // Fetch config
        $params = $event->getSuite()->getSettings();
        $url = parse_url($params['url']);
        $port = !empty($url['port']) ? $url['port'] : 80;

        if (self::canConnectToHttpd($url['host'], $port)) {
            throw new RuntimeException('Something is already running on ' . $params['url'] . '. Aborting tests.');
        }

        // Try to start the web server
        self::$pid = self::startBuiltInHttpd(
            $url['host'],
            $port,
            $params['documentRoot']
        );

        if (!self::$pid) {
            throw new RuntimeException('Could not start the web server');
        }

        $start = microtime(true);
        $connected = false;

        // Try to connect until the time spent exceeds the timeout specified in the configuration
        while (microtime(true) - $start <= (int)$params['timeout']) {
            if (self::canConnectToHttpd($url['host'], $port)) {
                $connected = true;
                break;
            }
        }

        if (!$connected) {
            self::killProcess(self::$pid);
            throw new RuntimeException(
                sprintf(
                    'Could not connect to the web server within the given timeframe (%d second(s))',
                    $params['timeout']
                )
            );
        } else {
            self::$mink = new Mink(array(
                'goutte' => new Session(new GoutteDriver(new GoutteClient()))
            ));
            self::$mink->setDefaultSessionName('goutte');
            self::$mink->getSession()->visit($params['url']);
        }
    }

    /**
     * Kill a process
     *
     * @param int $pid
     */
    private static function killProcess($pid)
    {
        exec('kill ' . (int)$pid);
    }

    /**
     * See if we can connect to the httpd
     *
     * @param string $host The hostname to connect to
     * @param int $port The port to use
     * @return boolean
     */
    private static function canConnectToHttpd($host, $port)
    {
        // Disable error handler for now
        set_error_handler(function () {
            return true;
        });

        // Try to open a connection
        $sp = fsockopen($host, $port);

        // Restore the handler
        restore_error_handler();

        if ($sp === false) {
            return false;
        }

        fclose($sp);

        return true;
    }

    /**
     * Start the built in httpd
     *
     * @param string $host The hostname to use
     * @param int $port The port to use
     * @param string $documentRoot The document root
     * @return int Returns the PID of the httpd
     */
    private static function startBuiltInHttpd($host, $port, $documentRoot)
    {
        // Build the command
        $command = sprintf('php -S %s:%d -t %s >/dev/null 2>&1 & echo $!',
            $host,
            $port,
            $documentRoot);

        $output = array();
        exec($command, $output);

        return (int)$output[0];
    }

    /**
     * Kill the httpd process if it has been started when the tests have finished
     *
     * @AfterSuite
     */
    public static function tearDown()
    {
        if (self::$pid) {
            self::killProcess(self::$pid);
        }
    }

    /**
     * @Given I am on the home page
     */
    public function iAmOnTheHomePage()
    {
        return self::$mink->getSession()->getCurrentUrl() == '/';
    }

    /**
     * @When I do nothing
     */
    public function iDoNothing()
    {
        return true;
    }

    /**
     * @Then I should see :arg1
     */
    public function iShouldSee($arg1)
    {
        $content = self::$mink->getSession()->getPage()->getText();
        return substr_count($content, $arg1) > 0;
    }

    /**
     * @When I click :arg1
     */
    public function iClick($arg1)
    {
        $el = self::$mink->getSession()->getPage()->findLink($arg1);
        if (null === $el) {
            throw new \InvalidArgumentException(sprintf('Could not find link: ' . $arg1));
        }
        $el->click();
    }
}
