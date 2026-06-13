import urllib.request
import json
import sys

def get_author_books(author_name, max_results=40):
    query = f'inauthor:"{author_name}"'
    url = (
        f'https://www.googleapis.com/books/v1/volumes'
        f'?q={urllib.parse.quote(query)}'
        f'&maxResults={max_results}'
        f'&printType=books'
        f'&orderBy=relevance'
        f'&key=AIzaSyCY7mF_-zSZqIFwd_n2tBYxB_ZxGFIkwcc'
    )

    req = urllib.request.Request(url, headers={'User-Agent': 'Mozilla/5.0'})
    with urllib.request.urlopen(req, timeout=15) as resp:
        data = json.loads(resp.read())

    items = data.get('items', [])
    total = data.get('totalItems', 0)
    print(f"\n=== Google Books: '{author_name}' ===")
    print(f"Toplam bulunan: {total} | Gösterilen: {len(items)}\n")

    for i, item in enumerate(items, 1):
        info    = item.get('volumeInfo', {})
        title   = info.get('title', '—')
        authors = ', '.join(info.get('authors', ['?']))
        year    = info.get('publishedDate', '?')[:4]
        cover   = info.get('imageLinks', {}).get('thumbnail', '')
        print(f"{i:2}. {title}")
        print(f"    Yazar(lar): {authors}")
        print(f"    Yıl: {year}  |  Kapak: {'VAR' if cover else 'YOK'}")
        print()

if __name__ == '__main__':
    import urllib.parse
    author = sys.argv[1] if len(sys.argv) > 1 else 'Immanuel Kant'
    get_author_books(author)
