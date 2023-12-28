/* Provide md4c to PHP via FFI
   Copied many portions from Martin Mitas:
       https://github.com/mity/md4c/blob/master/md2html/md2html.c

   Compile like this:
       cc -fPIC -Wall -O2 -shared php_md4c_toHtml.c -o php_md4c_toHtml.so -lmd4c-html

   This routine is not thread-safe. For threading we either need a thread-id passed
   or using a mutex to guard the static/global mbuf.

   Elmar Klausmeier, 11-Jul-2021
*/

#include <stdio.h>
#include <stdlib.h>
#include <string.h>
//#include <md4c.h>
#include <md4c-html.h>



struct membuffer {
	char* data;
	size_t asize;
	size_t size;
};



static void membuf_init(struct membuffer* buf, MD_SIZE new_asize) {
	buf->size = 0;
	buf->asize = new_asize;
	if ((buf->data = malloc(buf->asize)) == NULL) {
		fprintf(stderr, "membuf_init: malloc() failed.\n");
		exit(1);
	}
}



static void membuf_grow(struct membuffer* buf, size_t new_asize) {
	buf->data = realloc(buf->data, new_asize);
	if(buf->data == NULL) {
		fprintf(stderr, "membuf_grow: realloc() failed.\n");
		exit(1);
	}
	buf->asize = new_asize;
}



static void membuf_append(struct membuffer* buf, const char* data, MD_SIZE size) {
	if(buf->asize < buf->size + size)
		membuf_grow(buf, buf->size + buf->size / 2 + size);
	memcpy(buf->data + buf->size, data, size);
	buf->size += size;
}



static void process_output(const MD_CHAR* text, MD_SIZE size, void* userdata) {
	membuf_append((struct membuffer*) userdata, text, size);
}



static struct membuffer mbuf = { NULL, 0, 0 };


char *md4c_toHtml(const char *markdown) {	// return HTML string
	int ret;
	if (mbuf.asize == 0) membuf_init(&mbuf,16777216);

	mbuf.size = 0;	// prepare for next call
	ret = md_html(markdown, strlen(markdown), process_output,
		&mbuf, MD_DIALECT_GITHUB | MD_FLAG_NOINDENTEDCODEBLOCKS, 0);
	membuf_append(&mbuf,"\0",1); // make it a null-terminated C string, so PHP can deduce length
	if (ret < 0) return "<br>- - - Error in Markdown - - -<br>\n";

	return mbuf.data;
}


