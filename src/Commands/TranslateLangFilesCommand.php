<?php

namespace Habib\LaravelCrud\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Finder\SplFileInfo;

#[AsCommand(
    name: 'lang:translate',
    description: 'Translates the language files to other languages.',
)]
class TranslateLangFilesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lang:translate {--source=} {--lang=} {--except=} {--include=} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Translates the language files to other languages.';

    private $source = 'en';

    private $lang = 'ar';

    private $except = [];

    private $include = [];

    /**
     * Execute the console command.
     */
    public function handle(): int
    {

        $this->source = $this->option('source') ?? $this->source;
        $this->lang = $this->option('lang') ?? $this->lang;
        $this->except = $this->option('except') ?? $this->except;
        $this->include = $this->option('include') ?? $this->include;

        if (! $this->lang) {
            $lang = $this->ask('What language do you want to translate to?', $this->lang);
            if (! $lang) {
                $this->error('You must provide a language');

                return self::FAILURE;
            }
            if (! in_array($lang, array_keys(['ar', 'en']))) {
                $this->error('Language not supported');

                return self::FAILURE;
            }
            if ($lang == 'en') {
                $this->error('You can\'t translate to english');

                return self::FAILURE;
            }
            $this->lang = $lang;
        }

        if (! $this->except) {
            $except = $this->ask('What files do you want to exclude? (separate by comma) (optional)', $this->except[0] ?? '');
            if (! $except) {
                $this->except = [];
            } else {
                $this->except = explode(',', $except);
            }
        }

        if (! $this->include) {
            $include = $this->ask('What files do you want to include? (separate by comma) (optional)', $this->include[0] ?? '');
            if (! $include) {
                $this->include = [];
            } else {
                $this->include = explode(',', $include);
            }
        }
        if ($this->source == $this->lang) {
            $this->error('You can\'t translate from '.$this->source.' to '.$this->lang);

            return self::FAILURE;
        }

        $this->info('Translating from '.$this->source.' to '.$this->lang);

        foreach (File::allFiles(lang_path($this->source)) as $file) {
            $this->processFile($file);
        }

        return self::SUCCESS;

    }

    public function processFile(SplFileInfo $file): void
    {

        $filename = $file->getFilename();
        $filenameBody = $file->getFilenameWithoutExtension();

        if (in_array($filename, $this->except) || in_array($filenameBody, $this->except)) {
            $this->info('Skipping '.$filename.' because it is in the except list');

            return;
        }

        if (count($this->include)) {
            if (! in_array($filename, $this->include) && ! in_array($filenameBody, $this->include)) {
                $this->info('Translating '.$filename.' because it is in the include list');
                $this->translateFile($file->getFilename(), $this->source, $this->lang);

                return;
            }
        }

        $this->info('Translating '.$filename.' because it is not in the except list');
        $this->translateFile($file->getFilename(), $this->source, $this->lang);

    }

    public function translateFile(string $file, string $source, string $target): void
    {

        $targetFile = lang_path($target.'/'.$file);
        $fileContents = include lang_path($source.'/'.$file);
        if (! File::exists($targetFile)) {
            File::ensureDirectoryExists(pathinfo($targetFile, PATHINFO_DIRNAME));
            File::replace($targetFile, '<?php return '.var_export([], true).';');
        }
        $fileContentsTarget = include $targetFile;

        foreach ($fileContents as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $key2 => $value2) {
                    if (is_array($value2)) {
                        foreach ($value2 as $key3 => $value3) {
                            if (isset($fileContentsTarget[$key][$key2][$key3]) && ! $this->option('force')) {
                                $fileContents[$key][$key2][$key3] = $fileContentsTarget[$key][$key2][$key3];

                                continue;
                            }
                            $text = $this->translateText($value3, $source, $target);
                            if (empty($text)) {
                                continue;
                            }
                            $fileContents[$key][$key2][$key3] = $text;
                        }
                    } else {
                        if (isset($fileContentsTarget[$key][$key2]) && ! $this->option('force')) {
                            $fileContents[$key][$key2] = $fileContentsTarget[$key][$key2];

                            continue;
                        }
                        $text = $this->translateText($value2, $source, $target);
                        if (empty($text)) {
                            continue;
                        }
                        $fileContents[$key][$key2] = $text;
                    }
                }
            } else {
                if (isset($fileContentsTarget[$key]) && ! $this->option('force')) {
                    $fileContents[$key] = $fileContentsTarget[$key];

                    continue;
                }
                $text = $this->translateText($value, $source, $target);
                if (empty($text)) {
                    continue;
                }
                $fileContents[$key] = $text;
            }
        }

        File::ensureDirectoryExists(pathinfo($targetFile, PATHINFO_DIRNAME));
        File::replace($targetFile, '<?php return '.var_export($fileContents, true).';');

    }

    public function translateText(string $value, string $source, string $target): ?string
    {
        $value = $this->encodeValueForTranslation($value);

        $translated = $this->translateTextWithGoogleTranslate($value, $source, $target);
        $translated = str_replace('<: ', '<:', $translated);
        $translated = $this->decodeValueForSaving($translated);

        $this->info('Translated: '.$value.' to '.$translated);

        return $translated;

    }

    public function translateTextWithGoogleTranslate(string $content, string $source, string $target): string
    {
        $response = Http::retry(3)
            ->throw()
            ->get('https://translate.googleapis.com/translate_a/single?client=gtx&sl='.$source.'&tl='.$target.'&dt=t&q='.urlencode($content));
        $response = json_decode($response->body());
        $translatedText = '';
        foreach ($response[0] as $translation) {
            $translatedText .= $translation[0];
        }

        return ! empty($translatedText) ? $translatedText : $content;
    }

    public function encodeValueForTranslation(string $content): string
    {
        return preg_replace('/:([a-zA-Z0-9_-]*)/', '<:$1>', $content);
    }

    public function decodeValueForSaving(string $content): string
    {
        return preg_replace('/<([:a-zA-Z0-9_-]*)>/', '$1', $content);
    }
}
