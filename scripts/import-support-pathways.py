#!/usr/bin/env python3
"""Extract supplied bilingual Word drafts without promoting them to evidence.

Usage: python3 scripts/import-support-pathways.py ZIP GATEWAY_DOCX OUTPUT_JSON
Only the Python standard library is required. Source bytes are never executed.
"""
import hashlib
import io
import json
import re
import sys
import zipfile
from pathlib import Path
from xml.etree import ElementTree

NS = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}
NODE = re.compile(r"(?:[PDFG]\d{2}|AGE_[CTA]|H|R|E|U|C|END)$")
HEADINGS = {
    "مسارات العمر اختر المناسب فقط", "أسئلة مرتبطة بهذا البند", "خطوة دعم منزلية",
    "أسئلة فهم الموقف والمتابعة", "نهايات المسارات", "المراجع وحدود الاستخدام",
    "Age paths choose one", "Questions specific to this item", "Home support step",
    "Situation and follow up questions", "Path endings", "References and limits of use",
    "خريطة اختيار الفروع", "Selecting exploration routes",
}


def paragraphs(data):
    with zipfile.ZipFile(io.BytesIO(data)) as doc:
        root = ElementTree.fromstring(doc.read("word/document.xml"))
    return ["".join(t.text or "" for t in p.findall(".//w:t", NS)).strip()
            for p in root.findall(".//w:p", NS)]


def nodes(lines, prefix):
    result = {}
    language = "ar"
    seen = set()
    for i, line in enumerate(lines):
        if not NODE.fullmatch(line):
            continue
        if line in seen and language == "ar":
            language = "en"
        seen.add(line)
        key = prefix + ":" + line
        end = next((j for j in range(i + 1, len(lines)) if NODE.fullmatch(lines[j])), len(lines))
        block = lines[i + 1:end]
        # Section headers and references belong to the document, not a node.
        stop = next((j for j, text in enumerate(block)
                     if text in HEADINGS or text.startswith("http")
                     or (j > 0 and (text.startswith("المراجع") or text.startswith("References")))), len(block))
        block = [text for text in block[:stop] if text]
        node = result.setdefault(key, {
            "id": key, "local_id": line,
            "kind": "internal_decision" if prefix == "gateway" and line in {"G11", "G13", "H"}
                    else "question" if line.startswith(("P", "D", "F", "G", "AGE_")) else "outcome",
            "review_status": "draft", "text": {}, "source_block": {},
        })
        node["text"][language] = block[0] if block else ""
        node["source_block"][language] = block
    for key, node in result.items():
        if set(node["text"]) != {"ar", "en"} or not all(node["text"].values()):
            raise ValueError("Missing bilingual node: " + key)
    return list(result.values())


def main():
    archive_path, gateway_path, output_path = map(Path, sys.argv[1:])
    raw = archive_path.read_bytes()
    package = {"version": "2026-10-07.1", "review_status": "draft",
               "clinical_evidence": False, "archive_sha256": hashlib.sha256(raw).hexdigest(),
               "pathways": []}
    with zipfile.ZipFile(io.BytesIO(raw)) as archive:
        for filename in sorted(archive.namelist()):
            match = re.fullmatch(r"(\d{2})_(.+)_Arabic_English\.docx", filename)
            if not match:
                continue
            number, slug = match.groups()
            data = archive.read(filename)
            lines = paragraphs(data)
            tree_nodes = nodes(lines, slug)
            second_start = [i for i, line in enumerate(lines) if line == "P01"][1]
            # The English document title precedes the English introductory prose.
            english_title = next(lines[i - 1] for i, line in enumerate(lines[:second_start])
                                 if line.startswith("Conversation tree and home support"))
            package["pathways"].append({
                "id": slug, "source_number": int(number),
                "title": {"ar": lines[0], "en": english_title},
                "source_file": filename, "source_sha256": hashlib.sha256(data).hexdigest(),
                "origin": "supplied_word_draft", "review_status": "draft",
                "age_groups": ["child", "teen", "adult", "older_adult"],
                "clinical_evidence": False, "nodes": tree_nodes,
                "references": sorted(set(re.findall(r"https?://[^\s]+", "\n".join(lines)))),
            })
    if len(package["pathways"]) != 39:
        raise ValueError("Expected exactly 39 supplied pathways")
    gateway_raw = gateway_path.read_bytes()
    package["gateway"] = {
        "source_file": gateway_path.name,
        "source_sha256": hashlib.sha256(gateway_raw).hexdigest(),
        "review_status": "draft", "nodes": nodes(paragraphs(gateway_raw), "gateway"),
    }
    output_path.parent.mkdir(parents=True, exist_ok=True)
    output_path.write_text(json.dumps(package, ensure_ascii=False, indent=2) + "\n")
    print("Extracted 39 draft pathways and bilingual gateway")


if __name__ == "__main__":
    main()
