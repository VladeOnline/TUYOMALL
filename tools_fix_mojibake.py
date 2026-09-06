from pathlib import Path


ROOT = Path("JAQUE")
EXTENSIONS = {".html", ".php", ".js", ".css", ".md"}
TARGET_CHARS = (
    "áéíóúÁÉÍÓÚñÑüÜ"
    "¿¡©·₡—–‘’“”•✓✕×←⭐✨🚀🛒💬📊👋🔍🎁🔑📬📄🛍️"
)

CP1252_DECODE = {
    0x80: "\u20ac", 0x82: "\u201a", 0x83: "\u0192", 0x84: "\u201e",
    0x85: "\u2026", 0x86: "\u2020", 0x87: "\u2021", 0x88: "\u02c6",
    0x89: "\u2030", 0x8A: "\u0160", 0x8B: "\u2039", 0x8C: "\u0152",
    0x8E: "\u017d", 0x91: "\u2018", 0x92: "\u2019", 0x93: "\u201c",
    0x94: "\u201d", 0x95: "\u2022", 0x96: "\u2013", 0x97: "\u2014",
    0x98: "\u02dc", 0x99: "\u2122", 0x9A: "\u0161", 0x9B: "\u203a",
    0x9C: "\u0153", 0x9E: "\u017e", 0x9F: "\u0178",
}


def sloppy_cp1252_decode(data: bytes) -> str:
    chars = []
    for byte in data:
        if 0x80 <= byte <= 0x9F:
            chars.append(CP1252_DECODE.get(byte, chr(byte)))
        else:
            chars.append(bytes([byte]).decode("latin1"))
    return "".join(chars)


def build_replacements() -> dict[str, str]:
    replacements: dict[str, str] = {}
    for char in TARGET_CHARS:
        encoded = char.encode("utf-8")
        replacements[encoded.decode("latin1")] = char
        replacements[sloppy_cp1252_decode(encoded)] = char

    replacements.update(
        {
            "â”€": "-",
            "â•": "=",
            "â€": '"',
            "â„¢": "TM",
            "Â ": " ",
            "Â": "",
            "ï¸": "",
            "Ã": "Á",
            "Ã‰": "É",
            "Ã": "Í",
            "Ã“": "Ó",
            "Ãš": "Ú",
            "Ã‘": "Ñ",
        }
    )
    return replacements


def main() -> None:
    replacements = build_replacements()
    changed = []
    for path in ROOT.rglob("*"):
        if path.suffix.lower() not in EXTENSIONS:
            continue
        text = path.read_text(encoding="utf-8")
        original = text
        for bad, good in sorted(replacements.items(), key=lambda item: len(item[0]), reverse=True):
            text = text.replace(bad, good)
        if text != original:
            path.write_text(text, encoding="utf-8", newline="")
            changed.append(str(path))
    print("\n".join(changed))


if __name__ == "__main__":
    main()
