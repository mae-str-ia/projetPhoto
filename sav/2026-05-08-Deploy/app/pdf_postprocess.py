#!/usr/bin/env python3
"""
Post-process PDF to insert blank pages from a page map.
"""
import json
import re
import sys
from pypdf import PdfReader, PdfWriter

def create_blank_page(page_width, page_height):
    """Create a blank page with the same dimensions"""
    from pypdf import PageObject
    blank = PageObject.create_blank_page(width=page_width, height=page_height)
    return blank

def load_blanks_before(page_map_path):
    if not page_map_path:
        return {}

    with open(page_map_path, 'r', encoding='utf-8') as f:
        data = json.load(f)

    return {
        int(page): int(count)
        for page, count in data.get('blanksBefore', {}).items()
        if int(count) > 0
    }

def is_blank_page_text(text):
    normalized = re.sub(r'\s+', ' ', text or '').strip()
    return normalized == '' or re.fullmatch(r'\d+', normalized) is not None

def process_pdf(input_pdf, output_pdf, add_blank_pages=False, page_map_path=None):
    """
    Post-process PDF:
    - If page_map_path is provided, insert blanks before the listed source pages.
    - Legacy mode can still insert one blank after each source page.
    """
    try:
        reader = PdfReader(input_pdf)
        writer = PdfWriter()
        blanks_before = load_blanks_before(page_map_path)

        total_pages = len(reader.pages)

        for page_num in range(total_pages):
            page = reader.pages[page_num]
            pdf_page_num = page_num + 1  # Convert to 1-indexed
            width = float(page.mediabox.width)
            height = float(page.mediabox.height)

            for _ in range(blanks_before.get(pdf_page_num, 0)):
                writer.add_page(create_blank_page(width, height))

            # Add the original page
            if is_blank_page_text(page.extract_text()):
                writer.add_page(create_blank_page(width, height))
            else:
                writer.add_page(page)

            if add_blank_pages:
                blank = create_blank_page(width, height)
                writer.add_page(blank)

        # Write output
        with open(output_pdf, 'wb') as f:
            writer.write(f)

        new_page_count = len(writer.pages)
        return True, f"Processed {total_pages} pages -> {new_page_count} pages (added {new_page_count - total_pages} blanks)"

    except Exception as e:
        return False, str(e)

def detect_blank_pages(pdf_path):
    """Return pages with no body text. A lone page number still counts as blank."""
    reader = PdfReader(pdf_path)
    blank_pages = []

    for index, page in enumerate(reader.pages, start=1):
        if is_blank_page_text(page.extract_text()):
            blank_pages.append(index)

    return blank_pages

def extract_page_numbers(pdf_path):
    """Extract information about pages for mapping"""
    try:
        reader = PdfReader(pdf_path)
        total_pages = len(reader.pages)

        # Return page count info
        return {
            'total_pages': total_pages,
            'even_pages_count': total_pages // 2
        }
    except Exception as e:
        return {'error': str(e)}

if __name__ == '__main__':
    if len(sys.argv) == 3 and sys.argv[1] == '--blank-pages':
        try:
            print(json.dumps({'blankPages': detect_blank_pages(sys.argv[2])}))
            sys.exit(0)
        except Exception as e:
            print(f"ERROR: {e}")
            sys.exit(1)

    if len(sys.argv) < 3:
        print("Usage: python pdf_postprocess.py <input_pdf> <output_pdf> [page_map_json|add_blank_pages=1]")
        print("       python pdf_postprocess.py --blank-pages <input_pdf>")
        sys.exit(1)

    input_pdf = sys.argv[1]
    output_pdf = sys.argv[2]
    option = sys.argv[3] if len(sys.argv) > 3 else None
    page_map_path = option if option and option not in ('0', '1') else None
    add_blank_pages = option == '1' if option is not None else False

    success, message = process_pdf(input_pdf, output_pdf, add_blank_pages, page_map_path)

    if success:
        print(f"SUCCESS: {message}")
        sys.exit(0)
    else:
        print(f"ERROR: {message}")
        sys.exit(1)
