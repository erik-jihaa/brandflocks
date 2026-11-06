/** Mock data — briefing/stramien 2Type+.png (Grid #2 Article) */
export const articleMock = {
  weekLabel: 'Week 14',
  headline: 'Post name 1',
  content:
    'Lorem ipsum dolor sit amet, consectetuer adipiscing elit, sed diam nonummy nibh euismod tincidunt ut laoreet dolore magna aliquam erat volutpat. Ut wisi enim ad minim veniam, quis nostrud exerci tation ullamcorper suscipit lobortis nisl ut aliquip ex ea commodo consequat. Duis autem vel eum iriure dolor in hendrerit in vulputate velit esse molestie consequat, vel illum dolore eu feugiat nulla facilisis at vero eros et accumsan et iusto odio dignissim qui blandit praesent luptatum zzril delenit augue duis dolore te feugait nulla facilisi',
  hashtags: '#nummer1, #nummer2, .#nummer3,',
  sourceUrl: 'https://example.com/bron-artikel',
  rationale:
    'Lorem ipsum dolor sit amet,\nconsectetuer adipiscing\nelit, sed diam nonummy\nnibh euismod tincidunt ut\nlaoreet dolore magna\naliquam erat volutpat. Ut\nwisi enim',
  tone: {
    options: ['b to b', 'b to c', 'internal', 'partners', 'personal'],
    selected: 'b to b',
    suggested: 'b to b',
  },
  angle: {
    options: ['archetype', 'adversary', 'general'],
    selected: 'archetype',
    suggested: 'archetype',
  },
  imageRatio: {
    options: ['square', 'landscape'],
    selected: 'square',
    suggested: 'square',
  },
  platform: {
    options: ['Linkedin', 'Instagram', 'X', 'Facebook', 'Blog'],
    selected: 'Linkedin',
    suggested: 'Linkedin',
  },
  imageUrlSquare: '/images/test-image-square.png',
  imageUrlLandscape: '/images/test-image-landscape.png',
} as const
