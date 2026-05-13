<?php

class MarkdownPdfManager {
    private const MARKDOWN_FILE = MARKDOWN_DIR . '/livre.md';
    private const BUILD_MARKDOWN_FILE = MARKDOWN_BUILD_DIR . '/livre.build.md';
    private const RAW_TYPST_FILE = MARKDOWN_BUILD_DIR . '/texte.raw.typ';
    private const TYPST_FILE = MARKDOWN_BUILD_DIR . '/texte.typ';
    private const TEXT_PDF = OUTPUTS_DIR . '/texte.pdf';
    private const TEXT_PDF_PROCESSED = OUTPUTS_DIR . '/texte.processed.pdf';
    private const TOC_DATA_FILE = MARKDOWN_BUILD_DIR . '/toc.json';
    private const PAGE_MAP_FILE = MARKDOWN_BUILD_DIR . '/page-map.json';

    public static function generateTextPdf($copyToSource = true) {
        self::ensureInputs();
        ensureDir(MARKDOWN_BUILD_DIR);
        ensureDir(OUTPUTS_DIR);
        self::ensureTypstAssets();

        $pandoc = self::findTool('pandoc');
        $typst = self::findTool('typst');

        self::writeBuildMarkdown(null, true);
        self::runPandoc($pandoc);
        self::writeTypstFile(true);

        self::run([
            $typst,
            'compile',
            self::TYPST_FILE,
            self::TEXT_PDF,
        ]);

        $metadata = self::queryBuildMetadata($typst);
        $blankSourcePages = self::detectBlankPdfPages(self::TEXT_PDF);
        $pageMap = self::buildFinalPageMap($metadata['sections'], self::countPdfPages(self::TEXT_PDF), $blankSourcePages);
        $headings = self::applyFinalPagesToHeadings($metadata['headings'], $pageMap);
        $manualToc = self::buildManualTocMarkdown($headings);

        self::writeBuildMarkdown($manualToc, true);
        self::runPandoc($pandoc);
        self::writeTypstFile(true);

        self::run([
            $typst,
            'compile',
            self::TYPST_FILE,
            self::TEXT_PDF,
        ]);

        $metadata = self::queryBuildMetadata($typst);
        $blankSourcePages = self::detectBlankPdfPages(self::TEXT_PDF);
        $pageMap = self::buildFinalPageMap($metadata['sections'], self::countPdfPages(self::TEXT_PDF), $blankSourcePages);
        $headings = self::applyFinalPagesToHeadings($metadata['headings'], $pageMap);
        $manualToc = self::buildManualTocMarkdown($headings);
        self::writeTocData($headings);
        self::writePageMapData($pageMap);

        self::writeBuildMarkdown($manualToc, false);
        self::runPandoc($pandoc);
        self::writeTypstFile(false, $pageMap['sourceToFinal']);

        self::run([
            $typst,
            'compile',
            self::TYPST_FILE,
            self::TEXT_PDF,
        ]);

        self::postProcessPdfWithPageMap($pageMap);

        $pageCount = self::countPdfPages(self::TEXT_PDF_PROCESSED);

        if ($copyToSource) {
            if (!copy(self::TEXT_PDF_PROCESSED, SOURCE_PDF)) {
                throw new Exception('Impossible de mettre a jour ' . SOURCE_PDF);
            }
            PdfManager::clearCache();
        }

        return [
            'markdown' => self::MARKDOWN_FILE,
            'typst' => self::TYPST_FILE,
            'pdf' => self::TEXT_PDF_PROCESSED,
            'textPdf' => self::TEXT_PDF,
            'toc' => self::TOC_DATA_FILE,
            'pageMap' => self::PAGE_MAP_FILE,
            'sourcePdfUpdated' => (bool) $copyToSource,
            'finalPageCount' => $pageCount,
        ];
    }

    public static function addBlankPagesToTextPdf() {
        $python = 'C:\\Devs\\Python\\Python399\\python.exe';
        $script = __DIR__ . '/../pdf_postprocess.py';
        $inputPdf = self::TEXT_PDF;
        $outputPdf = self::TEXT_PDF_PROCESSED;

        if (!file_exists($inputPdf)) {
            throw new Exception('PDF source introuvable: ' . $inputPdf);
        }

        $cmd = implode(' ', array_map('escapeshellarg', [
            $python,
            $script,
            $inputPdf,
            $outputPdf,
            '1'  // add_blank_pages = true
        ]));

        $output = [];
        $return = 0;
        exec($cmd . ' 2>&1', $output, $return);

        if ($return !== 0) {
            throw new Exception("PDF post-processing échoué: " . implode("\n", $output));
        }

        return [
            'original' => $inputPdf,
            'processed' => $outputPdf,
            'message' => implode("\n", $output),
        ];
    }

    private static function ensureInputs() {
        if (!file_exists(self::MARKDOWN_FILE)) {
            throw new Exception('Markdown introuvable: ' . self::MARKDOWN_FILE);
        }
    }

    private static function runPandoc($pandoc) {
        self::run([
            $pandoc,
            self::BUILD_MARKDOWN_FILE,
            '--from',
            'markdown',
            '--to',
            'typst',
            '--metadata',
            'title=Une Vie en Mouvement',
            '--output',
            self::RAW_TYPST_FILE,
        ]);
    }

    private static function writeBuildMarkdown($manualToc = null, $emitSectionMetadata = false) {
        $markdown = file_get_contents(self::MARKDOWN_FILE);
        if ($markdown === false) {
            throw new Exception('Impossible de lire ' . self::MARKDOWN_FILE);
        }

        $markdown = preg_replace_callback(
            '/^\s*>?\s*\\\\\*\\\\\*\\\\\*\\\\\*\\\\\*(?:\s*\{\s*deco\s*=\s*"([^"]+)"\s*\})?\s*$/mu',
            function ($matches) {
                $asset = $matches[1] ?? 'deco6.svg';
                return self::rawTypstBlock(self::separatorMotifTypst($asset));
            },
            $markdown
        );

        $markdown = preg_replace_callback(
            '/<!--\s*section:\s*([^>]+)\s*-->\s*<!--\s*toc\s*-->/u',
            function ($matches) use ($manualToc, $emitSectionMetadata) {
                $typst = ['#pagebreak()'];
                if ($emitSectionMetadata) {
                    $typst[] = self::sectionMetadataTypst($matches[1]);
                }
                if ($manualToc === null) {
                    $typst[] = '#outline(title: [Sommaire], depth: 2)';
                    return self::rawTypstBlock(implode("\n", $typst));
                }
                return self::rawTypstBlock(implode("\n", $typst)) . "\n\n" . $manualToc . "\n\n";
            },
            $markdown
        );

        $markdown = preg_replace_callback(
            '/<!--\s*section:\s*([^>]+)\s*-->/u',
            function ($matches) use ($emitSectionMetadata) {
                if (!$emitSectionMetadata) {
                    return '';
                }
                return self::rawTypstBlock(self::sectionMetadataTypst($matches[1]));
            },
            $markdown
        );

        $markdown = preg_replace_callback(
            '/<!--\s*(blank-page|right-page|toc)\s*-->/u',
            function ($matches) use ($manualToc) {
                if ($matches[1] === 'toc' && $manualToc !== null) {
                    return self::rawTypstBlock('#pagebreak()') . "\n\n" . $manualToc . "\n\n";
                }
                return self::rawTypstDirective($matches[1]);
            },
            $markdown
        );

        // Inject markers for blockquotes with classes (dialogue, courrier, etc)
        $markdown = self::injectBlockquoteClassMarkers($markdown);
        $markdown = self::injectRunningHeaderMarkers($markdown);

        if (file_put_contents(self::BUILD_MARKDOWN_FILE, $markdown) === false) {
            throw new Exception('Impossible d ecrire ' . self::BUILD_MARKDOWN_FILE);
        }
    }

    private static function injectRunningHeaderMarkers($markdown) {
        $currentPart = '';
        $lines = preg_split('/\r\n|\r|\n/', $markdown);
        $result = [];

        foreach ($lines as $line) {
            if (preg_match('/^#\s+(.+)$/u', $line, $matches)) {
                $currentPart = self::cleanRunningHeaderTitle($matches[1], 'Partie');
            } elseif (preg_match('/^##\s+(.+)$/u', $line, $matches)) {
                    $chapter = self::cleanRunningHeaderTitle($matches[1], 'Chapitre');
                if ($currentPart !== '' && $chapter !== '') {
                    $result[] = self::rawTypstBlock(
                        '#metadata((part: "' . self::escapeTypstString($currentPart) . '", chapter: "' . self::escapeTypstString($chapter) . '")) <running-header>'
                    );
                }
            }

            $result[] = $line;
        }

        return implode("\n", $result);
    }

    private static function cleanRunningHeaderTitle($title, $prefix) {
        $title = trim(strip_tags((string)$title));
        $title = preg_replace('/[*_`]+/u', '', $title);
        if ($prefix === 'Partie') {
            $title = preg_replace('/^Partie\s+\d+\s*:\s*/iu', '', $title);
        } elseif ($prefix === 'Chapitre') {
            $title = preg_replace('/^Chapitre\s+\d+\s*:\s*/iu', '', $title);
        }
        return trim($title);
    }

    private static function injectBlockquoteClassMarkers($markdown) {
        // Convert blockquotes with classes to divs with embedded markers
        // Pattern: > lines, optional blank lines, then {.classname}

        $result = preg_replace_callback(
            '/^((?:>.*(?:\n|$))+?)(?:\n)*\{\.(\w+)\}\s*$/m',
            function($matches) {
                $blockquoteLines = $matches[1];
                $className = $matches[2];

                // Remove > prefix from blockquote lines
                $lines = explode("\n", trim($blockquoteLines));
                $processedLines = [];
                foreach ($lines as $line) {
                    if (strpos($line, '> ') === 0) {
                        $content = substr($line, 2);
                        // For dialogues: add blank line before speaker lines (starting with --)
                        if (strpos($content, '-- ') === 0 && !empty($processedLines) && !empty($processedLines[count($processedLines)-1])) {
                            $processedLines[] = '';
                        }
                        $processedLines[] = $content;
                    } elseif ($line === '>') {
                        $processedLines[] = '';
                    } else {
                        $processedLines[] = $line;
                    }
                }

                $content = implode("\n", $processedLines);

                // Return as div with embedded Typst marker
                return "::: {.$className}\n\n"
                    . "```{=typst}\n/* MARKER:$className */\n```\n\n"
                    . $content
                    . "\n\n:::\n";
            },
            $markdown
        );

        return $result;
    }

    private static function parseSectionAttr($attrs, $name, $default) {
        if (preg_match('/' . preg_quote($name, '/') . '\s*=\s*([^\s,]+)/u', $attrs, $match)) {
            return trim($match[1]);
        }
        return $default;
    }

    private static function sectionMetadataTypst($attrs) {
        $id = self::parseSectionAttr($attrs, 'id', 'default');
        $layout = self::parseSectionAttr($attrs, 'layout', 'text-right-only');
        return '#context metadata((kind: "section", id: "' . self::escapeTypstString($id) . '", layout: "' . self::escapeTypstString($layout) . '", page: counter(page).get().first()))';
    }

    private static function rawTypstDirective($name) {
        $typst = [
            'blank-page' => "#set page(footer: none, foreground: none)\n#pagebreak()\n#set page(footer: outside-page-number(), foreground: running-page-header())",
            'right-page' => '#pagebreak(to: "odd")',
            'toc' => "#pagebreak()\n#outline(title: [Sommaire], depth: 2)\n#pagebreak()",
        ][$name] ?? '';

        return self::rawTypstBlock($typst);
    }

    private static function rawTypstBlock($typst) {
        return "\n\n```{=typst}\n" . $typst . "\n```\n\n";
    }

    private static function separatorMotifTypst($asset = 'deco6.svg') {
        $asset = self::validateDecorationAsset($asset);
        return <<<TYPST
#v(0.35em)
#align(center)[
  #image("$asset", height: 1.5cm)
]
#v(0.35em)
TYPST;
    }

    private static function validateDecorationAsset($asset) {
        $asset = trim($asset);
        if (!preg_match('/^deco[0-9]+\.svg$/', $asset)) {
            throw new Exception('Decoration Typst invalide: ' . $asset);
        }
        return $asset;
    }

    private static function ensureTypstAssets() {
        $assets = ['deco6.svg', 'deco7.svg'];
        foreach ($assets as $asset) {
            $source = ROOT_DIR . '/img/' . $asset;
            $target = MARKDOWN_BUILD_DIR . '/' . $asset;
            if (!file_exists($source)) {
                throw new Exception('Asset Typst introuvable: ' . $source);
            }
            if (!file_exists($target) || filemtime($source) > filemtime($target)) {
                if (!copy($source, $target)) {
                    throw new Exception('Impossible de copier asset Typst: ' . $asset);
                }
            }
        }
    }

    /**
     * Apply layout rules to content based on sections (DEPRECATED - kept for reference)
     * For text-right-only: add pagebreak(to: "odd") after each h1 to force left page to be blank
     */
    private static function applySectionLayouts($content, $sections) {
        $result = '';

        foreach ($sections as $section) {
            $start = $section['start'];
            $end = $section['end'];
            $sectionContent = substr($content, $start, $end - $start);

            if ($section['layout'] === 'text-right-only') {
                // For text-right-only, add pagebreak after each h1
                $sectionContent = preg_replace(
                    '/^(#\s+[^\n]+)\n/m',
                    "$1\n#pagebreak(to: \"even\")\n",
                    $sectionContent
                );
            }

            // Remove section comment tags
            $sectionContent = preg_replace('/<!--\s*section:[^-]*-->\s*/u', '', $sectionContent);

            $result .= $sectionContent;
        }

        return $result;
    }

    /**
     * Parse sections from markdown with format: <!-- section: id=name, layout=text-right-only|text-both -->
     * Also extracts the first h1 title after each section for reliable mapping
     */
    private static function parseSections($content) {
        $sections = [];
        $currentSection = [
            'id' => 'default',
            'layout' => 'text-right-only',
            'start' => 0,
            'title' => null,
        ];

        preg_match_all('/<!--\s*section:\s*([^>]+)\s*-->/u', $content, $matches, PREG_OFFSET_CAPTURE);

        foreach ($matches[1] as $i => $match) {
            $attrs = $match[0];
            $commentPos = $matches[0][$i][1];
            $commentEnd = $commentPos + strlen($matches[0][$i][0]);

            if ($i > 0) {
                $currentSection['end'] = $commentPos;
                $sections[] = $currentSection;
            }

            $currentSection = [
                'id' => 'default',
                'layout' => 'text-right-only',
                'start' => $commentEnd,
                'title' => null,
            ];

            // Parse attributes: id=name, layout=text-both
            preg_match_all('/(\w+)\s*=\s*([^,]+)/u', $attrs, $attrs_matches);
            foreach ($attrs_matches[1] as $j => $key) {
                $value = trim($attrs_matches[2][$j]);
                if ($key === 'id') $currentSection['id'] = $value;
                if ($key === 'layout') $currentSection['layout'] = $value;
            }

            // Extract first h1 title after this section comment
            $afterComment = substr($content, $commentEnd);
            if (preg_match('/^[^#]*(?:^|\n)(#+)\s+([^\n]+)/m', $afterComment, $titleMatch)) {
                $headingLevel = strlen($titleMatch[1]);
                if ($headingLevel === 1) {
                    $currentSection['title'] = trim($titleMatch[2]);
                }
            }
        }

        $currentSection['end'] = strlen($content);
        $sections[] = $currentSection;

        return $sections;
    }

    private static function styleDialogueAndCourierBlocks($content) {
        // Style blocks based on markers
        // Pattern: #block[ ... /* MARKER:classname */ ... ]

        // For dialogue
        $content = preg_replace_callback(
            '/\#block\[\s*\/\*\s*MARKER:dialogue\s*\*\/(.*?)\n\]/s',
            function($matches) {
                $blockContent = $matches[1];
                return '#block(width: 100%, inset: (left: 0.5em, right: 2em), stroke: (left: 0.75pt + black))[#set par(leading: 0.4em, spacing: 0.6em)' . $blockContent . "\n]";
            },
            $content
        );

        // For courrier
        $content = preg_replace_callback(
            '/\#block\[\s*\/\*\s*MARKER:courrier\s*\*\/(.*?)\n\]/s',
            function($matches) {
                $blockContent = $matches[1];
                return '#block(width: 100%, inset: (left: 1em, right: 1em))[#set text(font: "Segoe Print", size: 10pt)' . $blockContent . "\n]";
            },
            $content
        );

        return $content;
    }

    private static function writeTypstFile($emitHeadingMetadata = false, $sourceToFinalPageMap = null) {
        $book = BookManager::load();
        $props = $book['properties'] ?? BookManager::defaultProperties();
        $margins = $props['textPageMargins'] ?? [
            'topCm' => 2,
            'rightCm' => 2,
            'bottomCm' => 2,
            'leftCm' => 2,
        ];
        $binding = (float)($props['textBindingCm'] ?? 3);

        $left = (float)($margins['leftCm'] ?? 2) + $binding;
        $right = (float)($margins['rightCm'] ?? 2);
        $top = (float)($margins['topCm'] ?? 2);
        $bottom = (float)($margins['bottomCm'] ?? 2);

        $content = file_get_contents(self::RAW_TYPST_FILE);
        if ($content === false) {
            throw new Exception('Impossible de lire ' . self::RAW_TYPST_FILE);
        }

        // Post-process blocks for dialogue and courrier styling
        $content = self::styleDialogueAndCourierBlocks($content);

        // Replace book title with custom formatting (multiline flag for ^ to match line start)
        // Matches: "= Une Vie en Mouvement" or "Une Vie en Mouvement\n===..." or " Une Vie en Mouvement..."
        $content = preg_replace(
            '/^\s*(?:= )?Une Vie en Mouvement\s*(?:\R={3,}|\R<une-vie-en-mouvement>)?\s*/mu',
            "#align(center)[#text(size: 26pt, weight: \"bold\")[Une Vie en Mouvement]]\n#v(1.2cm)\n\n",
            $content,
            1
        );

        $headingMetadata = $emitHeadingMetadata
            ? 'context metadata((kind: "heading", level: it.level, page: counter(page).get().first(), body: it.body))'
            : '';
        $pageMapTypst = self::pageMapTypst($sourceToFinalPageMap);

        $prelude = <<<TYPST
#let final-page-map = {$pageMapTypst}
#let running-header-label = <running-header>
#let outside-page-number() = context {
  let source-page = counter(page).get().first()
  let n = if source-page <= final-page-map.len() { final-page-map.at(source-page - 1) } else { source-page }
  if calc.odd(n) {
    align(right)[#n]
  } else {
    none
  }
}
#let running-page-header() = context {
  let source-page = counter(page).get().first()
  let n = if source-page <= final-page-map.len() { final-page-map.at(source-page - 1) } else { source-page }
  if calc.odd(n) and n >= 9 {
    let headers = query(selector(running-header-label).before(here()))
    if headers.len() > 0 {
      let h = headers.last().value
      place(top + left, dx: 5cm, dy: 0.7cm)[#text(size: 8pt, style: "italic", fill: rgb("#666666"))[Une Vie en Mouvement]]
      place(top + right, dx: -2cm, dy: 0.7cm)[#text(size: 8pt, style: "italic", fill: rgb("#666666"))[#h.part #sym.dash.em #h.chapter]]
    }
  } else {
    none
  }
}

#set page(width: 24cm, height: 16cm, margin: (left: {$left}cm, right: {$right}cm, top: {$top}cm, bottom: {$bottom}cm), footer: outside-page-number(), foreground: running-page-header())
#set text(font: "Libertinus Serif", size: 12pt, lang: "fr")
#set par(justify: true, leading: 0.62em)
#set footnote.entry(indent: 0em)
#show footnote.entry: it => {
  par(hanging-indent: 0.5em)[#it]
}
#set heading(numbering: none)
#show heading: it => {
  {$headingMetadata}
  if it.level == 1 {
    block(above: 2.4em, below: 1.8em)[#text(size: 24pt, weight: "bold")[#it.body]]
  } else if it.level == 2 {
    block(above: 1.8em, below: 1.3em)[#text(size: 18pt, weight: "bold")[#it.body]]
  } else {
    it
  }
}
#show outline.entry: it => {
  let physical-page = context counter(page).at(it.element.location()).first()
  block(width: 100%)[#h((it.level - 1) * 1em)#it.element.body #box(width: 1fr, it.fill) #physical-page]
  v(0.55em)
}

TYPST;

        if (file_put_contents(self::TYPST_FILE, $prelude . $content) === false) {
            throw new Exception('Impossible d ecrire ' . self::TYPST_FILE);
        }
    }

    private static function pageMapTypst($sourceToFinalPageMap) {
        if (!is_array($sourceToFinalPageMap) || empty($sourceToFinalPageMap)) {
            return '()';
        }
        $values = [];
        ksort($sourceToFinalPageMap);
        foreach ($sourceToFinalPageMap as $page) {
            $values[] = (string)(int)$page;
        }
        return '(' . implode(', ', $values) . ',)';
    }

    private static function queryBuildMetadata($typst) {
        $output = self::run([$typst, 'query', self::TYPST_FILE, 'metadata']);
        $rawOutput = implode("\n", $output);

        // Extract JSON from output (ignore warnings that come after)
        if (preg_match('/^\s*\[.*\]\s*(?:warning:|$)/s', $rawOutput, $matches)) {
            $jsonStr = trim($matches[0]);
            // Remove trailing warnings
            $jsonStr = preg_replace('/\s+warning:.*$/s', '', $jsonStr);
        } else {
            $jsonStr = $rawOutput;
        }

        $data = json_decode($jsonStr, true);
        if (!is_array($data)) {
            throw new Exception('Impossible de lire la sortie typst query');
        }

        $headings = [];
        $sections = [];
        foreach ($data as $item) {
            $value = $item['value'] ?? null;
            if (!is_array($value)) continue;
            if (($value['kind'] ?? '') === 'heading') {
                $level = (int)($value['level'] ?? 0);
                if ($level < 1 || $level > 2) continue;
                $title = self::typstContentToText($value['body'] ?? null);
                if ($title === '' || $title === 'Sommaire') continue;
                $sourcePage = (int)($value['page'] ?? 0);
                if ($sourcePage <= 0) continue;
                $headings[] = [
                    'level' => $level,
                    'title' => $title,
                    'sourcePage' => $sourcePage,
                ];
            } elseif (($value['kind'] ?? '') === 'section') {
                $sourcePage = (int)($value['page'] ?? 0);
                if ($sourcePage <= 0) continue;
                $sections[] = [
                    'id' => (string)($value['id'] ?? 'default'),
                    'layout' => (string)($value['layout'] ?? 'text-right-only'),
                    'sourcePage' => $sourcePage,
                ];
            }
        }

        usort($sections, fn($a, $b) => $a['sourcePage'] <=> $b['sourcePage']);

        return [
            'headings' => $headings,
            'sections' => $sections,
        ];
    }

    private static function buildFinalPageMap($sections, $sourcePageCount, $blankSourcePages = []) {
        $sourceToFinal = [];
        $blanksBefore = [];
        $sectionByPage = [];
        $sectionIndex = 0;
        $finalPage = 1;
        $blankLookup = array_fill_keys(array_map('intval', $blankSourcePages), true);

        for ($sourcePage = 1; $sourcePage <= $sourcePageCount; $sourcePage++) {
            while ($sectionIndex + 1 < count($sections) && $sections[$sectionIndex + 1]['sourcePage'] <= $sourcePage) {
                $sectionIndex++;
            }
            $section = $sections[$sectionIndex] ?? ['id' => 'default', 'layout' => 'text-right-only'];
            $layout = $section['layout'] ?? 'text-right-only';
            $isBlank = isset($blankLookup[$sourcePage]);

            if (!$isBlank && $layout !== 'text-both' && $finalPage % 2 === 0) {
                $blanksBefore[$sourcePage] = ($blanksBefore[$sourcePage] ?? 0) + 1;
                $finalPage++;
            }

            $sourceToFinal[$sourcePage] = $finalPage;
            $sectionByPage[$sourcePage] = [
                'id' => $section['id'] ?? 'default',
                'layout' => $layout,
                'blank' => $isBlank,
            ];
            $finalPage++;
        }

        return [
            'sourceToFinal' => $sourceToFinal,
            'blanksBefore' => $blanksBefore,
            'sectionByPage' => $sectionByPage,
            'blankSourcePages' => array_values(array_map('intval', $blankSourcePages)),
            'finalPageCount' => $finalPage - 1,
        ];
    }

    private static function applyFinalPagesToHeadings($headings, $pageMap) {
        foreach ($headings as &$heading) {
            $sourcePage = (int)$heading['sourcePage'];
            $heading['finalPage'] = (int)($pageMap['sourceToFinal'][$sourcePage] ?? $sourcePage);
        }
        unset($heading);
        return $headings;
    }

    private static function typstContentToText($node) {
        if (is_string($node)) return $node;
        if (!is_array($node)) return '';
        if (isset($node['text']) && is_string($node['text'])) return $node['text'];
        if (($node['func'] ?? '') === 'space') return ' ';
        if (isset($node['body'])) return self::typstContentToText($node['body']);
        $text = '';
        foreach (($node['children'] ?? []) as $child) {
            $text .= self::typstContentToText($child);
        }
        return trim(preg_replace('/\s+/u', ' ', $text));
    }

    private static function buildManualTocMarkdown($headings) {
        $lines = [
            self::rawTypstBlock('#heading(outlined: false)[Sommaire]'),
        ];
        foreach ($headings as $heading) {
            if ($heading['title'] === 'Remerciements') {
                continue;
            }
            $lines[] = self::rawTypstBlock(self::tocEntryTypst($heading));
        }
        return implode("\n", $lines);
    }

    private static function tocEntryTypst($heading) {
        $title = self::escapeTypstText($heading['title']);
        $page = (int)$heading['finalPage'];
        if ((int)$heading['level'] === 1) {
            return '#v(0.55em)' . "\n"
                . '#block(width: 100%)[#text(weight: "bold")[' . $title . ']#h(1fr)#text(weight: "bold")[' . $page . ']]';
        }

        return '#block(width: 100%)[#h(1.2em)#text(size: 10pt)[' . $title . '] #box(width: 1fr, line(length: 100%, stroke: (paint: rgb("#bbbbbb"), dash: "dotted"))) #text(size: 10pt)[' . $page . ']]';
    }

    private static function escapeTypstText($text) {
        return str_replace(
            ['\\', '[', ']'],
            ['\\\\', '\[', '\]'],
            $text
        );
    }

    private static function escapeTypstString($text) {
        return str_replace(
            ['\\', '"'],
            ['\\\\', '\\"'],
            $text
        );
    }

    private static function writeTocData($headings) {
        if (file_put_contents(self::TOC_DATA_FILE, json_encode(['headings' => $headings], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
            throw new Exception('Impossible d ecrire ' . self::TOC_DATA_FILE);
        }
    }

    private static function writePageMapData($pageMap) {
        if (file_put_contents(self::PAGE_MAP_FILE, json_encode($pageMap, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false) {
            throw new Exception('Impossible d ecrire ' . self::PAGE_MAP_FILE);
        }
    }

    private static function postProcessPdfWithPageMap($pageMap) {
        $python = 'C:\\Devs\\Python\\Python399\\python.exe';
        $script = __DIR__ . '/../pdf_postprocess.py';
        self::writePageMapData($pageMap);

        self::run([$python, $script, self::TEXT_PDF, self::TEXT_PDF_PROCESSED, self::PAGE_MAP_FILE]);
    }

    private static function detectBlankPdfPages($pdfPath) {
        $python = 'C:\\Devs\\Python\\Python399\\python.exe';
        $script = __DIR__ . '/../pdf_postprocess.py';
        $output = self::run([$python, $script, '--blank-pages', $pdfPath]);
        $data = json_decode(implode("\n", $output), true);
        if (!is_array($data)) {
            throw new Exception('Impossible de detecter les pages blanches de ' . $pdfPath);
        }
        return $data['blankPages'] ?? [];
    }

    private static function writeBlankPageTypst() {
        $content = "#set page(width: 24cm, height: 16cm, margin: 0cm)\n#box(width: 0pt, height: 0pt)\n";
        if (file_put_contents(self::BLANK_TYPST_FILE, $content) === false) {
            throw new Exception('Impossible d ecrire ' . self::BLANK_TYPST_FILE);
        }
    }

    private static function writeAcknowledgmentsTypst() {
        $content = <<<TYPST
#set page(width: 24cm, height: 16cm, margin: (left: 5cm, right: 2cm, top: 2cm, bottom: 2cm), footer: align(right)[3])
#set text(size: 11pt, lang: "fr")
#set par(justify: true, leading: 0.62em)
#v(2cm)
#align(center)[#text(size: 20pt, weight: "bold")[Remerciements]]
#v(1cm)

TYPST;
        if (file_put_contents(self::ACK_TYPST_FILE, $content) === false) {
            throw new Exception('Impossible d ecrire ' . self::ACK_TYPST_FILE);
        }
    }

    private static function syncBookPdfPages() {
        $book = BookManager::load();
        $pageTypes = self::buildBookPageTypesFromPageMap();

        if (!empty($pageTypes)) {
            $maxPage = max(array_keys($pageTypes));
            $book['printablePages'] = $maxPage;
            $existingMaxPage = 0;
            foreach ($book['pages'] as $page) {
                $existingMaxPage = max($existingMaxPage, (int)($page['pageNumber'] ?? 0));
            }
            $book['totalPages'] = max((int)($book['totalPages'] ?? 0), $existingMaxPage, $maxPage);
            if ($book['totalPages'] % 2 === 0) {
                $book['totalPages']++;
            }
            $existingByNumber = [];
            foreach ($book['pages'] as $index => $page) {
                $existingByNumber[(int)$page['pageNumber']] = $index;
            }

            for ($pageNumber = 1; $pageNumber <= $maxPage; $pageNumber++) {
                if (!isset($existingByNumber[$pageNumber])) {
                    $book['pages'][] = self::defaultBookPage($pageNumber, $pageTypes[$pageNumber] ?? 'photo');
                    $existingByNumber[$pageNumber] = count($book['pages']) - 1;
                }

                $index = $existingByNumber[$pageNumber];
                $type = $pageTypes[$pageNumber] ?? 'photo';
                $book['pages'][$index]['pageNumber'] = $pageNumber;
                $book['pages'][$index]['side'] = $pageNumber % 2 === 0 ? 'left' : 'right';
                $book['pages'][$index]['type'] = $type;

                if ($type === 'text') {
                    $book['pages'][$index]['pdfPage'] = $pageNumber;
                    unset($book['pages'][$index]['layout'], $book['pages'][$index]['photos'], $book['pages'][$index]['slotAssignments']);
                } else {
                    unset($book['pages'][$index]['pdfPage']);
                    if (!isset($book['pages'][$index]['layout'])) {
                        $book['pages'][$index]['layout'] = '4-grille';
                    }
                    if (!isset($book['pages'][$index]['photos'])) {
                        $book['pages'][$index]['photos'] = [];
                    }
                }
            }

            for ($pageNumber = $maxPage + 1; $pageNumber <= $book['totalPages']; $pageNumber++) {
                if (!isset($existingByNumber[$pageNumber])) {
                    $book['pages'][] = self::defaultBookPage($pageNumber, 'photo');
                    $existingByNumber[$pageNumber] = count($book['pages']) - 1;
                }

                $index = $existingByNumber[$pageNumber];
                $book['pages'][$index]['pageNumber'] = $pageNumber;
                $book['pages'][$index]['side'] = $pageNumber % 2 === 0 ? 'left' : 'right';
                if (($book['pages'][$index]['type'] ?? '') === 'text') {
                    $book['pages'][$index]['type'] = 'photo';
                    unset($book['pages'][$index]['pdfPage']);
                    if (!isset($book['pages'][$index]['layout'])) {
                        $book['pages'][$index]['layout'] = '4-grille';
                    }
                    if (!isset($book['pages'][$index]['photos'])) {
                        $book['pages'][$index]['photos'] = [];
                    }
                }
            }

            usort($book['pages'], fn($a, $b) => $a['pageNumber'] <=> $b['pageNumber']);
            BookManager::save($book);
            return;
        }

        foreach ($book['pages'] as &$page) {
            if (($page['type'] ?? '') === 'text' && ($page['side'] ?? '') === 'right') {
                $page['pdfPage'] = (int)$page['pageNumber'];
            }
        }
        unset($page);
        BookManager::save($book);
    }

    private static function buildBookPageTypesFromPageMap() {
        if (!file_exists(self::PAGE_MAP_FILE)) {
            return [];
        }

        $pageMap = json_decode(file_get_contents(self::PAGE_MAP_FILE), true);
        if (!is_array($pageMap)) {
            return [];
        }

        $finalPageCount = (int)($pageMap['finalPageCount'] ?? 0);
        if ($finalPageCount <= 0) {
            return [];
        }

        $types = array_fill(1, $finalPageCount, 'photo');
        $blankSourcePages = array_fill_keys(array_map('intval', $pageMap['blankSourcePages'] ?? []), true);
        foreach (($pageMap['sourceToFinal'] ?? []) as $sourcePage => $finalPage) {
            $sourcePage = (int)$sourcePage;
            $finalPage = (int)$finalPage;
            if ($finalPage <= 0) continue;
            if (isset($blankSourcePages[$sourcePage])) continue;
            $types[$finalPage] = 'text';
        }

        return $types;
    }

    private static function defaultBookPage($pageNumber, $type) {
        $page = [
            'pageNumber' => $pageNumber,
            'side' => $pageNumber % 2 === 0 ? 'left' : 'right',
            'type' => $type,
        ];
        if ($type === 'text') {
            $page['pdfPage'] = $pageNumber;
        } else {
            $page['layout'] = '4-grille';
            $page['photos'] = [];
        }
        return $page;
    }

    private static function writeSectionMetadata($sections, $outputFile) {
        $metadata = [
            'sections' => array_map(function ($section) {
                return [
                    'id' => $section['id'],
                    'layout' => $section['layout'],
                    'title' => $section['title'],
                ];
            }, $sections),
        ];

        if (file_put_contents($outputFile, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            throw new Exception('Impossible d ecrire ' . $outputFile);
        }
    }

    private static function countPdfPages($pdfPath) {
        $pdfinfo = self::findPdfinfo();
        $output = self::run([$pdfinfo, $pdfPath], false);
        foreach ($output as $line) {
            if (preg_match('/^Pages:\s*(\d+)/i', $line, $m)) {
                return (int)$m[1];
            }
        }
        return 0;
    }

    private static function findPdfinfo() {
        $candidates = [
            'C:\\Program Files\\Calibre2\\app\\bin\\pdfinfo.exe',
            'C:\\Program Files (x86)\\Calibre2\\app\\bin\\pdfinfo.exe',
            'pdfinfo',
        ];
        return self::firstExecutable($candidates, 'pdfinfo');
    }

    private static function findTool($name) {
        $local = getenv('LOCALAPPDATA') ?: '';
        $localShort = self::windowsShortLocalAppData();
        $programFiles = getenv('ProgramFiles') ?: 'C:\\Program Files';
        $candidates = [];

        if ($name === 'pandoc') {
            $candidates[] = 'C:\\Users\\MA05FB~1\\AppData\\Local\\MICROS~1\\WinGet\\Packages\\JOHNMA~1.SOU\\PANDOC~1.2\\pandoc.exe';
            if ($localShort !== '') {
                $candidates[] = $localShort . '\\Microsoft\\WinGet\\Packages\\JohnMacFarlane.Pandoc_Microsoft.Winget.Source_8wekyb3d8bbwe\\pandoc-3.9.0.2\\pandoc.exe';
                $candidates[] = $localShort . '\\Microsoft\\WinGet\\Packages\\JohnMacFarlane.Pandoc_Microsoft.Winget.Source_8wekyb3d8bbwe\\pandoc-*\\pandoc.exe';
            }
            $candidates[] = $local . '\\Microsoft\\WinGet\\Packages\\JohnMacFarlane.Pandoc_Microsoft.Winget.Source_8wekyb3d8bbwe\\pandoc-3.9.0.2\\pandoc.exe';
            $candidates[] = $local . '\\Microsoft\\WinGet\\Packages\\JohnMacFarlane.Pandoc_Microsoft.Winget.Source_8wekyb3d8bbwe\\pandoc-*\\pandoc.exe';
            $candidates[] = $programFiles . '\\Pandoc\\pandoc.exe';
        } elseif ($name === 'typst') {
            $candidates[] = 'C:\\Users\\MA05FB~1\\AppData\\Local\\MICROS~1\\WinGet\\Packages\\TYPSTT~1.SOU\\TYPST-~1\\typst.exe';
            if ($localShort !== '') {
                $candidates[] = $localShort . '\\Microsoft\\WinGet\\Packages\\Typst.Typst_Microsoft.Winget.Source_8wekyb3d8bbwe\\typst-x86_64-pc-windows-msvc\\typst.exe';
                $candidates[] = $localShort . '\\Microsoft\\WinGet\\Packages\\Typst.Typst_Microsoft.Winget.Source_8wekyb3d8bbwe\\typst-*\\typst.exe';
            }
            $candidates[] = $local . '\\Microsoft\\WinGet\\Packages\\Typst.Typst_Microsoft.Winget.Source_8wekyb3d8bbwe\\typst-x86_64-pc-windows-msvc\\typst.exe';
            $candidates[] = $local . '\\Microsoft\\WinGet\\Packages\\Typst.Typst_Microsoft.Winget.Source_8wekyb3d8bbwe\\typst-*\\typst.exe';
        } elseif ($name === 'qpdf') {
            $candidates[] = $programFiles . '\\qpdf*\\bin\\qpdf.exe';
        }

        $candidates[] = $name;

        return self::firstExecutable($candidates, $name);
    }

    private static function firstExecutable($candidates, $label) {
        foreach ($candidates as $candidate) {
            foreach (self::expandCandidate($candidate) as $path) {
                if (file_exists($path)) {
                    return $path;
                }
            }

            $where = trim(shell_exec('where ' . escapeshellarg($candidate) . ' 2>nul') ?? '');
            if ($where !== '') {
                $lines = preg_split('/\r?\n/', $where);
                if (!empty($lines[0])) {
                    return trim($lines[0]);
                }
            }
        }

        throw new Exception("Outil introuvable: $label");
    }

    private static function windowsShortLocalAppData() {
        if (PHP_OS_FAMILY !== 'Windows') {
            return '';
        }

        $short = trim(shell_exec('cmd /c for %I in ("%LOCALAPPDATA%") do @echo %~sI 2>nul') ?? '');
        return is_dir($short) ? $short : '';
    }

    private static function expandCandidate($candidate) {
        if (strpos($candidate, '*') === false) {
            return [$candidate];
        }
        $matches = glob($candidate);
        return $matches ?: [];
    }

    private static function run($args, $throw = true, $cwd = null) {
        $cmd = implode(' ', array_map('escapeshellarg', $args)) . ' 2>&1';
        if ($cwd !== null) {
            $cmd = 'cd /d ' . escapeshellarg($cwd) . ' && ' . $cmd;
        }
        $output = [];
        $return = 0;
        exec($cmd, $output, $return);
        if ($throw && $return !== 0) {
            throw new Exception("Commande echouee ($return): " . implode(' ', $args) . "\n" . implode("\n", $output));
        }
        return $output;
    }
}
