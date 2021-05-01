<p align="center"><code>&hearts; Made with &lt;love/&gt; And I love &lt;code/&gt;</code></p>

# Laravel Rache

A super cool package for caching the laravel response dynamically.

## Usage

### Config

Publish the config file by running the following artisan command. It will publish the rache config file under <b>
config/rache.php</>.

```
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

One can create a Rache tag using the following artisan commands.

```
php artisan make:rache-tag {tag-name}
```

or

```
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
    'search' =>  \App\Rache\Tags\Search::class, // Newly added
],
```

#### Note

One can add any number of tags based on the no. of unique constraints they for the route by which the cache will be
considered as new.

### In Route

Use the middleware along with tags. You can enter the ttl as a tag for settings a different lifetime than the lifetime
configured in the rache.php config file. One can pass the ttl in any position along with the other tags.

#### Note:

1. The lifetime ttl entered are in seconds here.
2. One must have declared the route name in order to rache middleware against a route.
3. One can use the middleware without tags like `->middleware('rache')`. It will cache the response without considering
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

### Documentation Site ⚠️

The documentation site for this package is in under development!

## License

Copyright © Kumaravel

Laravel Rache is open-sourced software licensed under the [MIT license](LICENSE).