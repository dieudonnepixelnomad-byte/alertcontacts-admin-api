<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class StorageLinkAlternative extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:link-alt';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create storage symbolic link without using exec() function';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $publicPath = public_path('storage');
        $storagePath = storage_path('app/public');

        // Vérifier si le lien existe déjà
        if (File::exists($publicPath)) {
            if (is_link($publicPath)) {
                $this->info('The "public/storage" directory already exists and is a symbolic link.');
                return 0;
            } else {
                $this->error('The "public/storage" directory already exists but is not a symbolic link.');
                return 1;
            }
        }

        // Vérifier si le dossier storage/app/public existe
        if (!File::exists($storagePath)) {
            File::makeDirectory($storagePath, 0755, true);
            $this->info('Created storage/app/public directory.');
        }

        // Créer le lien symbolique en utilisant PHP natif
        try {
            if (function_exists('symlink')) {
                if (symlink($storagePath, $publicPath)) {
                    $this->info('The [public/storage] directory has been linked.');
                    return 0;
                } else {
                    $this->error('Failed to create symbolic link using symlink().');
                    return 1;
                }
            } else {
                // Fallback: créer un fichier .htaccess pour rediriger
                $this->createHtaccessRedirect($publicPath, $storagePath);
                return 0;
            }
        } catch (\Exception $e) {
            $this->error('Error creating symbolic link: ' . $e->getMessage());
            
            // Fallback: créer un fichier .htaccess pour rediriger
            $this->createHtaccessRedirect($publicPath, $storagePath);
            return 0;
        }
    }

    /**
     * Créer une redirection .htaccess comme fallback
     */
    private function createHtaccessRedirect($publicPath, $storagePath)
    {
        // Créer le dossier public/storage
        File::makeDirectory($publicPath, 0755, true);
        
        // Créer un fichier .htaccess pour rediriger vers storage/app/public
        $relativePath = '../' . str_replace(base_path() . '/', '', $storagePath);
        
        $htaccessContent = "RewriteEngine On\n";
        $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-f\n";
        $htaccessContent .= "RewriteCond %{REQUEST_FILENAME} !-d\n";
        $htaccessContent .= "RewriteRule ^(.*)$ {$relativePath}/$1 [L]\n";
        
        File::put($publicPath . '/.htaccess', $htaccessContent);
        
        $this->info('Created .htaccess redirect as fallback for symbolic link.');
        $this->warn('Note: Using .htaccess redirect instead of symbolic link. This may impact performance.');
    }
}