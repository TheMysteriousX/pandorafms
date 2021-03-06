// charset_char_traits.hh
// This file is part of libpbe; see http://anyterm.org/
// (C) 2007-2008 Philip Endecott

// Distributed under the Boost Software License, Version 1.0:
//
// Permission is hereby granted, free of charge, to any person or organization
// obtaining a copy of the software and accompanying documentation covered by
// this license (the "Software") to use, reproduce, display, distribute,
// execute, and transmit the Software, and to prepare derivative works of the
// Software, and to permit third-parties to whom the Software is furnished to
// do so, all subject to the following:
// 
// The copyright notices in the Software and this entire statement, including
// the above license grant, this restriction and the following disclaimer,
// must be included in all copies of the Software, in whole or in part, and
// all derivative works of the Software, unless such copies or derivative
// works are solely in the form of machine-executable object code generated by
// a source language processor.
// 
// THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
// IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
// FITNESS FOR A PARTICULAR PURPOSE, TITLE AND NON-INFRINGEMENT. IN NO EVENT
// SHALL THE COPYRIGHT HOLDERS OR ANYONE DISTRIBUTING THE SOFTWARE BE LIABLE
// FOR ANY DAMAGES OR OTHER LIABILITY, WHETHER IN CONTRACT, TORT OR OTHERWISE,
// ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
// DEALINGS IN THE SOFTWARE.

#ifndef libpbe_charset_charset_char_traits_hh
#define libpbe_charset_charset_char_traits_hh

#include "charset_t.hh"
#include "charset_traits.hh"

#include <char_traits>


namespace pbe {

// Character Set Character Traits
// ------------------------------
//
// It would be great if we could write
//   std::char_traits<iso8859_1_char>
// but unfortunately we can't as iso8859_1_char is only a typedef, and
// std::char_traits can't be specialised differently for it than for char,
// iso8859_2_char, and all the others.
//
// So instead we can write
//   charset_char_traits<cs::iso8859_1>
// which is a traits class compatible with std::char_traits, for iso-8859-1
// characters.  We also have
//   charset_unit_traits<cs::iso8859_1>
// which gives the character traits for the character set's unit type, rather
// than its character type, which will be different in the case of variable
// length character sets like utf8.
// They are all defined in terms of charset_traits and std::char_traits.

template <charset_t cset>
struct charset_char_traits {
  typedef charset_traits<cset>::char_t        char_type;
  typedef std::char_traits<char_type>::int_t  int_type;
  typedef std::char_traits<char_type>::pos_t  pos_type;
  typedef std::char_traits<char_type>::off_t  off_type;
  typedef charset_traits<cset>::state_t       state_type;
};

template <charset_t cset>
struct charset_unit_traits {
  typedef charset_traits<cset>::unit_t        char_type;
  typedef std::char_traits<char_type>::int_t  int_type;
  typedef std::char_traits<char_type>::pos_t  pos_type;
  typedef std::char_traits<char_type>::off_t  off_type;
  typedef charset_traits<cset>::state_t       state_type;
};

// FIXME the state_types in the two versions should be DIFFERENT, right???


#endif
