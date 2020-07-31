/**
 * ownCloud - Music app
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Pauli Järvinen <pauli.jarvinen@gmail.com>
 * @copyright Pauli Järvinen 2020
 */


angular.module('Music').controller('AlbumDetailsController', [
	'$rootScope', '$scope', 'Restangular', 'gettextCatalog', 'libraryService',
	function ($rootScope, $scope, Restangular, gettextCatalog, libraryService) {

		function resetContents() {
			$scope.album = null;
			$scope.totalLength = null;
			$scope.lastfmInfo = null;
			$scope.albumInfo = null;
			$scope.albumTags = null;
		}
		resetContents();

		function setImageUrl(url) {
			if (url) {
				url = 'url("' + url + '")';
			}
			$('#app-sidebar .albumart').css('background-image', url);
		}

		function showDetails(albumId) {
			if (!$scope.album || albumId != $scope.album.id) {
				resetContents();

				$scope.album = libraryService.getAlbum(albumId);
				$scope.totalLength = _.reduce($scope.album.tracks, function(sum, track) {
					return sum + track.length;
				}, 0);

				if ($scope.album.cover) {
					var url = OC.generateUrl('apps/music/api/album/') + albumId + '/cover?originalSize=true';
					setImageUrl(url);
				} else {
					setImageUrl('');
				}

				// Because of the asynchronous nature of teh REST queries, it is possible that the
				// current album has already changed again by the time we get the result. If that has
				// happened, then the result should be ignored.
				Restangular.one('album', albumId).one('details').get().then(
					function(result) {
						if ($scope.album && $scope.album.id == albumId) {
							$scope.lastfmInfo = result;

							if ('album' in result) {
								if ('wiki' in result.album) {
									$scope.albumInfo = result.album.wiki.content || result.album.wiki.summary;
									// modify all links in the info so that they will open to a new tab
									$scope.albumInfo = $scope.albumInfo.replace(/<a href=/g, '<a target="_blank" href=');
								}
								else {
									var linkText = gettextCatalog.getString('See the album on Last.fm');
									$scope.albumInfo = '<a target="_blank" href="' + result.album.url + '">' + linkText +'</a>';
								}

								if ('tags' in result.album) {
									$scope.albumTags = formatTags(result.album.tags.tag);
								}
							}

							$scope.$parent.adjustFixedPositions();
						}
					}
				);

			}
		}

		function formatTags(tags) {
			// Filter out the tags intended to be used on Last.fm as personal tags. These make no sense
			// for us as we are not aware of the user's Last.fm account and we only show global tags.
			tags = _.reject(tags, {name: 'albums I own'});
			tags = _.reject(tags, {name: 'vinyls i own'});
			tags = _.reject(tags, {name: 'favorite albums'});
			tags = _.reject(tags, {name: 'favourite albums'});
			return formatLinkList(tags);
		}

		function formatLinkList(linkArray) {
			htmlLinks = _.map(linkArray, function(item) {
				return '<a href="' + item.url + '" target="_blank">' + item.name + '</a>';
			});
			return htmlLinks.join(', ');
		}

		$scope.$watch('contentId', showDetails);
	}
]);
