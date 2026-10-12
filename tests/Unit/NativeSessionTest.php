<?php

declare(strict_types=1);

namespace WilyCode\Session\Php\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use WilyCode\Session\Php\NativeSession;
use WilyCode\Session\SessionInterface;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class NativeSessionTest extends TestCase
{
    private string $sessionDirectory;

    protected function setUp(): void
    {
        $this->sessionDirectory = sys_get_temp_dir() . '/wilycode-session-php-' . bin2hex(random_bytes(8));

        if (!mkdir($this->sessionDirectory, 0700)) {
            self::fail('Could not create an isolated session directory.');
        }

        ini_set('session.save_path', $this->sessionDirectory);
        ini_set('session.use_cookies', '0');
        ini_set('session.use_only_cookies', '0');
        ini_set('session.use_strict_mode', '0');

        session_name('WILYCODESESSID');
        session_id('wilycode-' . bin2hex(random_bytes(8)));
    }

    protected function tearDown(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        foreach (glob($this->sessionDirectory . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        rmdir($this->sessionDirectory);
    }

    public function test_implements_session_contract_and_starts_lazily(): void
    {
        $session = new NativeSession();

        self::assertInstanceOf(SessionInterface::class, $session);
        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame('', $session->getNamespace());
        $session->set('key', 'value');
        self::assertSame(PHP_SESSION_ACTIVE, session_status());
        self::assertSame('value', $session->get('key'));
    }

    public function test_set_get_has_remove_and_null_values(): void
    {
        $session = new NativeSession();

        self::assertFalse($session->has('missing'));
        self::assertSame('fallback', $session->get('missing', 'fallback'));

        $session->set('count', 123);
        $session->set('nullable', null);

        self::assertSame(123, $session->get('count'));
        self::assertTrue($session->has('nullable'));
        self::assertNull($session->get('nullable', 'fallback'));

        $session->remove('nullable');
        self::assertFalse($session->has('nullable'));
    }

    public function test_namespace_views_are_independent_and_share_native_storage(): void
    {
        $root = new NativeSession();
        $auth = $root->withNamespace('auth');
        $cart = $root->withNamespace('cart');

        self::assertSame('', $root->getNamespace());
        self::assertSame('auth', $auth->getNamespace());
        self::assertSame('cart', $cart->getNamespace());
        self::assertSame('', $root->getNamespace());
        self::assertSame([], $auth->all());

        $auth->set('user_id', 10);
        $cart->set('items', [1, 2]);
        $root->set('language', 'en');

        self::assertSame(10, $root->withNamespace('auth')->get('user_id'));
        self::assertFalse($auth->has('items'));
        self::assertSame(['items' => [1, 2]], $cart->all());
        self::assertSame('en', $root->get('language'));
    }

    public function test_clear_only_removes_values_in_the_selected_namespace(): void
    {
        $session = new NativeSession();
        $auth = $session->withNamespace('auth');
        $cart = $session->withNamespace('cart');

        $auth->set('user_id', 10);
        $cart->set('items', [1]);
        $auth->clear();

        self::assertSame([], $auth->all());
        self::assertSame(['items' => [1]], $cart->all());
    }

    public function test_replace_overwrites_only_current_namespace(): void
    {
        $session = new NativeSession();
        $auth = $session->withNamespace('auth');
        $other = $session->withNamespace('other');

        $auth->set('user_id', 10);
        $auth->set('role', 'admin');
        $other->set('enabled', true);
        $auth->replace(['user_id' => 99]);

        self::assertSame(['user_id' => 99], $auth->all());
        self::assertTrue($other->get('enabled'));
    }

    public function test_save_closes_session_and_next_access_reopens_saved_data(): void
    {
        $session = new NativeSession();
        $session->set('saved', ['id' => 42]);
        $session->save();

        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame(['id' => 42], $session->get('saved'));
        self::assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    public function test_save_before_start_is_safe(): void
    {
        $session = new NativeSession();
        $session->save();

        self::assertSame(PHP_SESSION_NONE, session_status());
    }

    public function test_regenerate_changes_session_identifier_and_keeps_values(): void
    {
        $session = new NativeSession();
        $session->set('user_id', 55);
        $oldId = session_id();

        $session->regenerate();

        self::assertNotSame($oldId, session_id());
        self::assertSame(55, $session->get('user_id'));
    }

    public function test_destroy_clears_data_and_closes_session(): void
    {
        $session = new NativeSession();
        $session->set('token', 'secret');
        $session->destroy();

        self::assertSame(PHP_SESSION_NONE, session_status());
        self::assertSame([], $_SESSION);
    }

    public function test_null_byte_in_namespace_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new NativeSession("invalid\0namespace");
    }
}
