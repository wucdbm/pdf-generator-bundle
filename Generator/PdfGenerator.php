<?php

namespace Wucdbm\Bundle\PdfGeneratorBundle\Generator;

use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Process\Process;

class PdfGenerator {

    /** @var string */
    protected $cacheDir;

    /** @var string */
    protected $rootDir;

    /** @var string */
    protected $binary;

    /** @var \Twig_Environment */
    protected $twig;

    /** @var EventDispatcherInterface */
    protected $eventDispatcher;

    /** @var RequestStack */
    protected $requestStack;

    public function __construct(string $cacheDir, string $rootDir, string $binary, \Twig_Environment $twig, EventDispatcherInterface $eventDispatcher, RequestStack $requestStack) {
        $this->cacheDir = $cacheDir;
        $this->rootDir = $rootDir;
        $this->binary = $binary;
        $this->twig = $twig;
        $this->eventDispatcher = $eventDispatcher;
        $this->requestStack = $requestStack;
    }

    public function bootstrap(string $html): PrintResult {
        return $this->wkPrint($this->layoutBootstrap($html));
    }

    public function wkPrint(string $html): PrintResult {
        $cwd = sprintf('%s/wkhtmltopdf', $this->cacheDir);

        if (!is_dir($cwd)) {
            mkdir($cwd, 0755, true);
        }

        $name = uniqid();
        $htmlName = sprintf('%s.html', $name);
        $pdfName = sprintf('%s.pdf', $name);
        $htmlFile = sprintf('%s/%s', $cwd, $htmlName);
        $pdfFile = sprintf('%s/%s', $cwd, $pdfName);

        $html = $this->replaceUrlsWithFilesystemPath($html);

        file_put_contents($htmlFile, $html);
        $command = sprintf('cd %s && xvfb-run %s %s %s', $cwd, $this->binary, $htmlName, $pdfName);

        $process = new Process($command);
        $process->run();

        if ($process->isSuccessful()) {
            $this->eventDispatcher->addListener(KernelEvents::TERMINATE, function () use ($htmlFile, $pdfFile) {
                if (file_exists($htmlFile)) {
                    unlink($htmlFile);
                }

                if (file_exists($pdfFile)) {
                    unlink($pdfFile);
                }
            }, 255);
        }

        return new PrintResult($pdfFile, $process);
    }

    private function layoutBootstrap(string $html): string {
        $data = [
            'html' => $html
        ];

        return $this->twig->render('@WucdbmPdfGenerator/layout_bootstrap.html.twig', $data);
    }

    private function replaceUrlsWithFilesystemPath($html): string {
        $request = $this->requestStack->getCurrentRequest();
        $schemeAndHost = $request->getSchemeAndHttpHost();

        $find = sprintf('%s/bundles', $schemeAndHost);

        $replace = sprintf('file://%s/../web/bundles', $this->rootDir);

        $html = str_replace('src="/bundles', 'src="' . $replace, $html);

        return str_replace($find, $replace, $html);
    }

}