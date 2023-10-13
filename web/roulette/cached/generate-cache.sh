#!/bin/sh
# Download and generate caches.
# Poets
curl --connect-timeout 10 -u "piv123:piv123" -s -S -f -o data/cached_poets_en_j.json "https://poetryinvoice.ca/cached/cached_poets_en_j.json"
curl --connect-timeout 10 -u "piv123:piv123" -s -S -f -o data/cached_poets_en_s.json "https://poetryinvoice.ca/cached/cached_poets_en_s.json"
curl --connect-timeout 10 -u "piv123:piv123" -s -S -f -o data/cached_poets_fr_j.json "https://poetryinvoice.ca/cached/cached_poets_fr_j.json"
curl --connect-timeout 10 -u "piv123:piv123" -s -S -f -o data/cached_poets_fr_s.json "https://poetryinvoice.ca/cached/cached_poets_fr_s.json"
# Tags
curl --connect-timeout 10 -u "piv123:piv123" -s -S -f -o data/cached_tags_en.json "https://poetryinvoice.ca/tags-for-roulette"
curl --connect-timeout 10 -u "piv123:piv123" -s -S -f -o data/cached_tags_fr.json "https://lesvoixdelapoesie.ca/tags-for-roulette"
# Moods
curl --connect-timeout 10 -u "piv123:piv123" -s -S -f -o data/cached_moods_en.json "https://poetryinvoice.ca/moods-for-roulette"
curl --connect-timeout 10 -u "piv123:piv123" -s -S -f -o data/cached_moods_fr.json "https://lesvoixdelapoesie.ca/moods-for-roulette"
