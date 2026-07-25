<?php

declare(strict_types=1);

namespace Rache\Tests\Feature;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Rache\Exceptions\InvalidTagException;
use Rache\Exceptions\TagNotRegisteredException;
use Rache\Facades\Rache;
use Rache\Tags\Auth as AuthTag;
use Rache\Tags\Pagination;
use Rache\Tags\Request as RequestTag;
use Rache\Tests\Fixtures\NotATag;
use Rache\Tests\Fixtures\UuidKeyedUser;
use Rache\Tests\TestCase;

/**
 * Covers tag resolution and the tags shipped with the package.
 */
class TagsTest extends TestCase
{
    #[Test]
    public function it_resolves_the_shipped_tags(): void
    {
        $this->assertInstanceOf(AuthTag::class, Rache::getTagInstance('auth'));
        $this->assertInstanceOf(Pagination::class, Rache::getTagInstance('page'));
        $this->assertInstanceOf(RequestTag::class, Rache::getTagInstance('request'));
    }

    #[Test]
    public function get_tag_instance_is_part_of_the_public_api(): void
    {
        // Regression: the readme has always documented
        // Rache::getTagInstance('auth')->getTagDetails($userId), but the method
        // was protected, so that documented call raised an Error.
        $this->assertTrue(
            (new \ReflectionMethod(\Rache\Rache::class, 'getTagInstance'))->isPublic()
        );

        $this->assertSame(['id' => 7], Rache::getTagInstance('auth')->getTagDetails(7));
    }

    #[Test]
    public function an_unregistered_tag_throws(): void
    {
        $this->expectException(TagNotRegisteredException::class);

        Rache::getTagInstance('nope');
    }

    #[Test]
    public function a_tag_class_that_does_not_exist_throws(): void
    {
        config()->set('rache.tags.ghost', 'App\\Rache\\Tags\\Ghost');

        $this->expectException(InvalidTagException::class);
        $this->expectExceptionMessage('does not exist');

        Rache::getTagInstance('ghost');
    }

    #[Test]
    public function a_tag_class_that_does_not_implement_the_contract_throws(): void
    {
        config()->set('rache.tags.bogus', NotATag::class);

        $this->expectException(InvalidTagException::class);
        $this->expectExceptionMessage('should implement the RacheTagInterface');

        Rache::getTagInstance('bogus');
    }

    #[Test]
    public function the_auth_tag_is_null_for_a_guest(): void
    {
        $this->assertSame(['id' => null], Rache::getTagData('auth'));
    }

    #[Test]
    public function the_auth_tag_uses_the_models_own_primary_key(): void
    {
        // Regression: the tag read $user->id directly, which is null for any
        // user model keyed by something other than "id". Every authenticated
        // visitor then produced identical tag data, so they all shared one
        // cache entry and could be served each other's responses.
        $user = new UuidKeyedUser;
        $user->forceFill(['uuid' => 'ce0f-1234']);

        $request = HttpRequest::create('/posts');
        $request->setUserResolver(fn (): Authenticatable => $user);

        $this->assertSame(['id' => 'ce0f-1234'], (new AuthTag($request))->getTagDetails());
    }

    #[Test]
    public function two_authenticated_users_do_not_share_a_cache_entry(): void
    {
        $hits = 0;

        Route::get('/me', function () use (&$hits) {
            $hits++;

            return response()->json(['user' => request()->user()?->getAuthIdentifier(), 'hits' => $hits]);
        })->middleware('rache:auth')->name('me');

        $first = new UuidKeyedUser;
        $first->forceFill(['uuid' => 'user-a']);

        $second = new UuidKeyedUser;
        $second->forceFill(['uuid' => 'user-b']);

        $this->actingAs($first)->getJson('/me')->assertJson(['user' => 'user-a']);
        $this->actingAs($second)->getJson('/me')->assertJson(['user' => 'user-b']);

        $this->assertSame(2, $hits);
    }

    #[Test]
    public function the_pagination_tag_stringifies_scalar_page_input(): void
    {
        // Regression: ?page=2 arrives as the string "2" from the query string
        // but as the integer 2 from a JSON body, and the two serialize
        // differently, so the same page produced two cache entries.
        $fromQuery = new Pagination(HttpRequest::create('/posts?page=2'));
        $fromJson = new Pagination(HttpRequest::create('/posts', 'GET', ['page' => 2]));

        $this->assertSame(['page' => '2'], $fromQuery->getTagDetails());
        $this->assertSame($fromQuery->getTagDetails(), $fromJson->getTagDetails());
    }

    #[Test]
    public function the_pagination_tag_ignores_non_scalar_page_input(): void
    {
        $tag = new Pagination(HttpRequest::create('/posts?page[]=2'));

        $this->assertSame(['page' => null], $tag->getTagDetails());
    }

    #[Test]
    public function the_request_tag_returns_all_input(): void
    {
        $tag = new RequestTag(HttpRequest::create('/posts?a=1&b=2'));

        $this->assertSame(['a' => '1', 'b' => '2'], $tag->getTagDetails());
    }
}
