#!/usr/bin/env python3
"""
Installation:
    pip3 install beautifulsoup4 requests pymysql
    chmod +x ./Lamp.py
    ./Lamp.py
"""
from bs4 import BeautifulSoup
import requests
import pymysql
import re
import argparse
import sys
import logging
import string

logger = logging.getLogger("")

urls=[
["http://www.learnaboutmovieposters.com/newsite/index/countries/US/history/studios/20cen-fox/20thCent-foxLetters.asp", "20th Century Fox"],
["http://www.learnaboutmovieposters.com/newsite/index/countries/US/history/studios/20CEN-FOX/20thCent-foxNos.asp", "20th Century Fox"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/AIP/AIP-ProductionLogs2.asp", "AIP"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/AIP/AIP-ProductionLogs.asp", "AIP"],
["http://www.learnaboutmovieposters.com/NewSite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/COLUMBIA/ColumbiaProdCode.asp", "Colombia"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/FOX/Fox-productionLetters.asp", "Fox"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/FOX/Fox-productionNumbers.asp", "Fox"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/GRAND-NATIONAL/GrandProdCode.asp", "Grand National"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/MGM/MGM-NosProdCode.asp", "MGM"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/MGM/MGM-Shorts-LettersProdCode.asp", "MGM"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/MGM/MGM-Shorts-NumbersProdCode.asp", "MGM"],
["http://www.learnaboutmovieposters.com/newsite/index/countries/US/history/studios/monogram/MonogramProductionNumbers.asp", "Monogram"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/MONOGRAM/Monogram-LettersProdCode.asp", "Monogram"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/PARAMOUNT/Paramount-LettersProdCode.asp", "Paramount"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/PARAMOUNT/Paramount-NosProdCode.asp", "Paramount"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/PRODUCERS/PRC-ProdCode.asp", "PRC"],
["http://www.learnaboutmovieposters.com/newsite/index/countries/US/history/studios/republic/Republic-productionCodes.asp", "Republic"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/RKO/RKO-LetterCodes.asp", "RKO"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/RKO/RKO-NumberCodes.asp", "RKO"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/UNIVERSAL/Universal-Letters-ProdCodes.asp", "Universal"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/UNIVERSAL/Universal-Nos-ProdCodes.asp", "Universal"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/WB/Warner-Letters-ProdCodes.asp", "Warner Bros"],
["http://www.learnaboutmovieposters.com/newsite/INDEX/COUNTRIES/US/HISTORY/STUDIOS/WB/Warner-Nos-ProdCodes.asp", "Warner Bros"],

]
"""
['prod#', 'title', 'director', 'studio', 'year', 'nss']
['prod #', 'title', 'director', 'studio', 'year', 'nss']
['prod. #', 'title', 'year rel.', 'nss #']
['prod. #', 'title', 'year rel.', 'nss #']
['prod. #', 'title', 'year', 'nss']
['prod #', 'title', 'director', 'studio', 'year']
['prod #', 'title', 'director', 'studio', 'year']
['prod no.', 'title', 'year']
['prod #', 'title', 'studio', 'year', 'nss']
['prod. #', 'category', 'title', 'rel. date', 'nss']
['prod #', 'category', 'title', 'rel date', 'nss']
['prod #', 'title', 'year', 'nss #']
['prod no.', 'title', 'year', 'nss']
['prod. #', 'desc', 'title', 'director', 'star', 'yr', 'nss']
['prod. #', 'desc', 'title', 'director', 'star', 'yr', 'nss']
['prod #', 'title', 'star', 'year', 'nss']
['code', 'title', 'release year/date', 'class#', 'nss']
['prod', 'title', '', 'year', 'nss #']
['prod #', 'title', 'director', 'year']
['prod #', 'title', 'director', 'year', 'nss']
['prod #', 'title', 'director', 'year', 'nss']
['prod #', 'title', 'year', 'nss']
['prod #', 'title', 'year', 'nss']
"""
colmap = {
    'prod#': 'still_code',
    'prod #': 'still_code',
    'prod.#': 'still_code',
    'prod. #': 'still_code',
    'prod no': 'still_code',
    'prod no.': 'still_code',
    'prod. no': 'still_code',
    'prod. no.': 'still_code',
    'prod': 'still_code',
    'code': 'still_code',
    'title': 'film_title',
    'studio': 'studio',
    'director': 'director',
    'category': None,
    'year': 'year',
    'yr': 'year',
    'year rel.': 'year',
    'nss': None,
    'nss #': None,
    'date': 'date',
    'rel. date': 'date',
    'rel date': 'date',
    'release year/date': 'date',
    'class#': None,
    'desc': None,
    'star': None,
    '': None,
}


class Lamp(object):
    def __init__(self, args, cursor):
        self.args = args
        self.cursor = cursor
        self.response = requests.post("http://www.learnaboutmovieposters.com/posters/sponsors/dologin.asp", 
                      data={'username':'bruce', 'password':'tada'})
        self.cookies = self.response.cookies
        self.counts = {'new':0, 'parsed':0}

    def get_soup(self, url):
        response = requests.get(url, cookies=self.cookies)
        return BeautifulSoup(response.text, "html5lib")

    def parse_singlepage(self, soup):
        columns = [colmap[x.text.lower().split("\n")[0].strip()] for x in soup.select("tr")[0].select("td")]
        for n, tr in enumerate(soup.select("table tr")):
            row = {}
            if n == 0:
                continue
            for c, td in enumerate(tr.select("td")):
                if columns[c] is None:
                    # TODO: maybe handle this?
                    continue
                row[columns[c]] = td.text

            yield self.clean(row)

    def clean(self, row):
        new = {}
        for k, val in row.items():
            new[k] = re.sub("[\n\t \xa0]+", " ", val).strip().replace("\x96", "-").replace("\x92", "'") \
                     .replace("\x91", "'").replace(" \x85 ",".").replace(" ... ",".").replace("\"","")
            new[k] = re.sub("\*$", "", new[k])
            new[k] = re.sub(r'[^\x20-\x7E\xA0-\xFF]', "", new[k])
            new[k] = re.sub(r'[ ]{2,}', " ", new[k])
            new[k] = new[k].upper()

            m = re.match("^(19|20)[0-9]{2} *- *([0-9]{1,2}/[0-9]{1,2}/[0-9]{1,2})$", val)
            if k == "date" and m:
                new[k] = m.groups()[1]
                print(m.groups()[1])
        
        new['cleaner_title'] = re.sub(" +\(.+\)$", "", new['film_title'])
        new['cleaner_title'] = re.sub("^the +", "", new['cleaner_title'], flags=re.IGNORECASE)
        new['cleaner_title'] = re.sub("^a +", "", new['cleaner_title'], flags=re.IGNORECASE)
        new['cleaner_title'] = re.sub("^an +", "", new['cleaner_title'], flags=re.IGNORECASE)
        new['cleaner_title'] = re.sub(",? +The$", "", new['cleaner_title'], flags=re.IGNORECASE)
        new['cleaner_title'] = re.sub(",? +A$", "", new['cleaner_title'], flags=re.IGNORECASE)
        new['cleaner_title'] = re.sub(",? +an$", "", new['cleaner_title'], flags=re.IGNORECASE)
        new['cleaner_title'] = re.sub(" +and +", " & ", new['cleaner_title'], flags=re.IGNORECASE)
        new['cleaner_title'] = re.sub(", +", " ", new['cleaner_title'], flags=re.IGNORECASE)
        new['cleaner_title'] = re.sub(" - .+", " ", new['cleaner_title'], flags=re.IGNORECASE)
        
        new['still_code_clean'] = re.sub("[^0-9a-zA-Z]+", "", new['still_code'], flags=re.IGNORECASE)

# Instead of using LAMP's years, fetch our year if there's an exact title match and use that instead
#        if 'film_title' in new:
#            m = None
#            if 'date' in new:
#                m = re.match("(19|20)([0-9]{2})", new['date'])
#            elif 'year' in new:
#                m = re.match("(19|20)([0-9]{2})", new['year'])
#            
#            if m:
#                new['film_title'] = new['film_title'] + " ('" + m.groups()[1] + ")"
        
        #new['tags'] = "automatically linked not checked"

        return new

    def parse_multipage(self, soup):
        studio = None

        for element in soup.body.children:
            if element.name == "table":
                for row in self.parse_subtable(element):
                    row['studio'] = studio
                    yield row

            elif element.name == "p" and element.text.strip().lower() not in ("new additions", "letters", "numbers", "", "uk"):
                studio = element.text.strip().split("\n")[0]

    def parse_subtable(self, table):
        columns = ['still_code', 'film_title', 'studio']
        for row in table.select("tr"):
            data = {}
            cols = row.select("td")
            for k, v in enumerate(columns):
                data[v] = cols[k].text
            data = self.clean(data)
            if data['still_code'] or data['film_title']:
                yield data

    def all_records(self):
        for url, studio in urls:
            soup = self.get_soup(url)
            logging.debug("Parsing %s", url)
            for x in self.parse_singlepage(soup):
                self.counts['parsed'] += 1
                yield x

        soup = self.get_soup("http://www.learnaboutmovieposters.com/NewSite/INDEX/STILLS/stills-08-additions.html")
        for x in self.parse_multipage(soup):
            self.counts['parsed'] += 1
            yield x

    def select(self, row):
        if 'date' in row.keys():
            del row['date']

#        self.cursor.execute("select id from listing_system.still_codes2 where "+
#                       " and ".join("coalesce(`"+k+"`, '') = %s" for k in row.keys())+" collate latin1_general_ci",
#                       tuple(row.values()))
        
        row_cleaned = row.copy()
        
        if 'year' in row_cleaned.keys():
            del row_cleaned['year']
        
        if 'studio' in row_cleaned.keys():
            del row_cleaned['studio']
            
        if 'film_title' in row_cleaned.keys():
            del row_cleaned['film_title']
        
        self.cursor.execute("select id from listing_system.still_codes2 where "+
                       " and ".join("coalesce(`"+k+"`, '') = %s" for k in row_cleaned.keys())+" collate latin1_general_ci",
                       tuple(row_cleaned.values()))


        if self.cursor.rowcount == 0:
            return False
        return row

    def find_description(self, row):
        self.cursor.execute("select autoID, TITLE, type_of_record, `eBay Description` "
                       "from listing_system.descriptions where TITLE = %s", row['film_title'])

        desc = None

        if self.cursor.rowcount:
            desc = self.cursor.fetchone()
            title = row['film_title']

        if desc is not None and row['cleaner_title'] != row['film_title']:
            self.cursor.execute("select autoID, TITLE, type_of_record, `eBay Description` "
                           "from listing_system.descriptions where TITLE = %s", row['cleaner_title'])

            if self.cursor.rowcount:
                desc = self.cursor.fetchone()
                logging.debug("Cleaner title worked: %s => %s", row['film_title'], row['cleaner_title'])
                title = row['cleaner_title']

        if desc is not None and desc['type_of_record'] == "redirect" and 'year' in row:
            yeartitle = title+" ('"+row['year'][2:4]+")"
            self.cursor.execute("select autoID, TITLE, type_of_record, `eBay Description` "
                           "from listing_system.descriptions where TITLE = %s", yeartitle)

            if self.cursor.rowcount:
                desc = self.cursor.fetchone()
                logging.debug("Dereferenced see title: %s => %s", row['film_title'], yeartitle)

            if 'date' in row:
                m = re.match(r'^.*(18|19|20)([0-9]{2})$', row['date'])
                if m:
                    yeartitle = title + " ('" + m.group(2) + ")"
                    self.cursor.execute("select autoID, TITLE, type_of_record, `eBay Description` "
                                   "from listing_system.descriptions where TITLE = %s", yeartitle)

                    if self.cursor.rowcount:
                        desc = self.cursor.fetchone()
                        logging.debug("Dereferenced see title (other method): %s => %s", row['film_title'], yeartitle)

        if desc is None:
            logging.debug("Could not find description for %s", row)

        return desc

    def insert(self, row):
        if 'date' in row.keys():
            del row['date']
        row['tags'] = "automatically linked not checked"
        q = ("insert into listing_system.still_codes2 set "+
                       ", ".join("`"+k+"` = %s" for k in row.keys() if k!='date'), tuple(row.values()))

        if args.sql:
            print(self.cursor.mogrify(*q))

        if args.debug > 1:
            logging.debug(row)

        if args.run:
            self.cursor.execute(*q)

    def handle_record(self, row):
        record = self.select(row)

        if record:
            return 0

        desc = self.find_description(row)

        if desc:
            row['descriptions_id'] = desc['autoID']
            

        self.insert(row)
        self.counts['new'] += 1


def main(args):
    db = pymysql.connect(user='listing', password='system', charset='latin1', autocommit=True)
    with db.cursor(cursor=pymysql.cursors.DictCursor) as cur:
        lamp = Lamp(args=args, cursor=cur)
        for row in lamp.all_records():
            lamp.handle_record(row)

    if args.report:
        print("parsed: {}; new: {}".format(lamp.counts['parsed'], lamp.counts['new']))


if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Parses all the still codes on LAMP and tries to link to descriptions "
                                                 "table and add new records to the still_codes2 table. ")

    group = parser.add_mutually_exclusive_group(required=True)
    group.add_argument('--sql', action='store_true',
                       help='Outputs SQL queries, one per line, without executing them.')
    group.add_argument('--run', action='store_true',
                       help='Inserts new records into the database, with no output except errors.')
    group.add_argument('--dry', action='store_true', help='Takes no action.')

    parser.add_argument('--debug', nargs='?', default=0, const=1, type=int,
                       help='Parses LAMP pages and outputs debugging information, but takes no action. Running '
                            '"--debug 2" will display more.')

    parser.add_argument('--report', action='store_true',
                        help='Print a count of new records and records parsed at completion.')

    args = parser.parse_args()

    if args.debug:
        logging.basicConfig(stream=sys.stdout, level=logging.DEBUG)
    else:
        logging.basicConfig(stream=sys.stdout, level=logging.WARNING)

    main(args)

