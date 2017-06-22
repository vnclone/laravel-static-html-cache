<?php

namespace vnclone\LaravelStaticHtmlCache\Http\Middleware;

use Closure;
use Illuminate\Filesystem\Filesystem;
class LaravelStaticHtmlCacheMiddleware
{

    /**
     * @var Filesystem
     */
    private $files;

    /**
     * @var bool
     */
    private $enabled;

    function __construct(Filesystem $files)
    {
        $this->files = $files;

        if(config('static-html-cache.enabled') === 'debug') {
            $this->enabled = !config('app.debug');
        } else {
            $this->enabled = config('static-html-cache.enabled');
        }
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request $request
     * @param  \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        return $next($request);
    }
    public function gzip($content){
        return gzencode($content, 6, FORCE_GZIP);
    }
    public function terminate(\Illuminate\Http\Request $request, \Illuminate\Http\Response $response)
    {
        if($this->enabled && !$request->ajax() && !isAdmin()){
            if ($this->shouldStoreResponse($request, $response)) {
                $filename = $this->getFilename($request);

                $this->ensureStorageDirectory($filename);

                $file = $response->getContent();
                $this->files->put($filename, $file);
                $this->files->put($filename.'.gz',$this->gzip( $file));
            }
        }
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @param \Illuminate\Http\Response $response
     * @return bool
     */
    private function shouldStoreResponse(\Illuminate\Http\Request $request, \Illuminate\Http\Response $response)
    {
        $isGETRequest = $request->getMethod() === 'GET';
        $hasNoParams = count($request->input()) === 0;
        $contenttype=$response->headers->get('content-type');
        $isHtmlMimeType = strpos($contenttype, 'text/xml') !== false  || strpos($contenttype, 'text/html') !== false;

        return $this->enabled && $response->isOk() && $isGETRequest && $hasNoParams && $isHtmlMimeType;
    }

    /**
     * @param \Illuminate\Http\Request $request
     * @return mixed
     */
    private function getFilename(\Illuminate\Http\Request $request)
    {
        $request_uri = trim($request->getRequestUri(), '/');
        $request_uri = empty($request_uri) ? '' : '/' . $request_uri;
        if(empty($request_uri)){
            $request_uri='/index.html';
        }else{
            if($request_uri[strlen($request_uri)-1]=='/'){
                $request_uri.='index.html';
            }else{
                $request_uri.='.html';
            }
        }
        $filename = public_path(config('static-html-cache.cache_path_prefix') . $request_uri  );
        return $filename;
    }

    /**
     * @param $filename
     */
    private function ensureStorageDirectory($filename)
    {
        $path = dirname($filename);

        if (!$this->files->isDirectory($path)) {
            $this->files->makeDirectory($path, 0777, true);
        }
    }
}
