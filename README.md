<p align="center"><code>&hearts; Made with &lt;love/&gt; And I love &lt;code/&gt;</code></p>

# Laravel Rache

A super cool package for caching the laravel response dynamically.

## Installation

### Composer Install

You can install the package via composer:

```bash
composer require theriddleofenigma/laravel-rache
```

### Service provider and Alias [only for lumen]

Add the service provider and alias in the <b>bootstrap/app.php</b>.

```injectablephp
// Service provider
$app->register(\Rache\RacheServiceProvider::class);

// Alias
$app->alias('Rache', \Rache\Facades\Rache::class);
```

#### Note

For laravel, the service provider and aliases will be loaded automatically by the Package Discovery.

### Config

Publish the config file by running the following artisan command. It will publish the rache config file under <b>
config/rache.php</b>.

```bash
php artisan rache:publish
```

### Middleware

You can either declare the default middleware, or you extend it by creating your own middleware for customisation.

```injectablephp
'rache' => \Rache\Middleware\CacheResponse::class,
```

### Rache Tags

Rache tags acts as label for settings up the cache against some data. `Auth`, `Request`, and `Pagination` tags are added
by default. You can find them in the rache.php config file under <i>tags</i> key.

You can create a Rache tag using the following artisan commands.

```bash
php artisan make:rache-tag {tag-name}
```

or

```bash
php artisan rache:make-tag {tag-name}
```

Once you have created the rache tag successfully, then you can configure the dataset in the newly created file
under `getTagDetails()` method. The array data returned from the `getTagDetails()` method should contain the key-value
pairs of unique dataset, and it will bring the cache dynamic on using with the rache middleware.

Let's add a Rache Tag for search,

Run `php artisan make:rache-tag Search` in the project base directory.

Then add the unique constraints for the search tag under `getTagDetails()`,

```injectablephp
/**
 * Get the tag details of this rache tag.
 *
 * @return array
 */
public function getTagDetails(): array
{
    return [
        'search' => $this->request->input('search'),
    ];
}
```

After that you should define the newly created tag in the rache.php config file as follows,

```injectablephp
/*
 * You may declare the tags here. All responses will be tagged.
 * These tags are very used while forgetting the response cache.
 */
'tags' => [
    'auth' => \Rache\Tags\Auth::class, // Added by default
    'page' => \Rache\Tags\Pagination::class, // Added by default
    'request' => \Rache\Tags\Request::class, // Added by default
    
    // Custom tags
    'search' =>  \App\Rache\Tags\Search::class, // Newly added
],
```

#### Note

You can add any number of tags based on the no. of unique constraints they for the route by which the cache will be
considered as new.

### In Route

Use the middleware along with tags. You can enter the ttl as a tag for settings a different lifetime than the lifetime
configured in the rache.php config file. You can pass the ttl in any position along with the other tags.

#### Note

1. The lifetime ttl entered are in seconds here.
2. You must have declared the route name in order to rache middleware against a route.
3. You can use the middleware without tags like `->middleware('rache')`. It will cache the response without considering
   any tag data. It will be useful if a route response won't vary for anyone.

```
// Both acts as same.
rache:ttl_10,auth,page,search
rache:auth,ttl_10,search,page
```

#### Example usage

```injectablephp
Route::get('/posts', 'PostController@index')
    ->middleware('rache:ttl_10,auth,page,search')
    ->name('posts.index');
```

### Flush the tags with route and data

You can flush the cache by using `Rache::flushTag({tag-name}, {options:[route, data]})`. We can find some real-time
examples for flushing the tags.

#### Case 1

Let's say you want to clear all the cache based on the auth tag,

```injectablephp
Rache::flushTag('auth', [
    'route' => 'posts.index',
]);
```

If the route name is not mentioned, then the cache for all the routes having the auth tag will get cleared.

#### Case 2

If you want to clear for the current authenticated user then,

```injectablephp
Rache::flushTag('auth', [
    'route' => 'posts.index',
    'data' => Rache::getTagData('auth'),
]);
```

The `Rache::getTagData()` will render the data as same as it's get rendered for creating the cache.

#### Case 3

In case you want to clear the data for other users,

```injectablephp
$userId = 2;
Rache::flushTag('auth', [
    'route' => 'posts.index',
    'data' => Rache::getTagInstance('auth')->getTagDetails($userId),
]);
```

If the route name is not mentioned, then the cache for all the routes having the auth tag will get cleared. Here, the
auth tag <b>for userId 2</b> will get cleared without touching the cache of other userId's.

#### Note

You can flush any type of tag along with route name or data based on your need. Ex: On creating new record or delete
event or on custom event or an API trigger. You can use it anywhere, whenever a tag has been flushed it will clear all
the corresponding cache.

## Credits

- [Kumaravel](https://github.com/theriddleofenigma)
- [All Contributors](../../contributors)

## License

Copyright © Kumaravel

Laravel Rache is open-sourced software licensed under the [MIT license](LICENSE).