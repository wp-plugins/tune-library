create table PREFIXtracks (
	title		text				not	null,
	artist		text,
	albumartist	text,
	album		text,
	trackid		int		unsigned	not	null	primary	key,
	tracknum	int		unsigned
);