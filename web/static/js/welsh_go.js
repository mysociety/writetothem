// Homepage enhancement: when the postcode being typed is in Wales, reveal an
// extra "Go" button that sends the user into the Welsh version of the site
// (cy.<domain>) carrying the postcode they entered.
//
// Wales postcode lookup — generated from ONSPD May 2025. Stores only the
// Wales-range boundaries as a flat sorted array:
//   [wales_start_1, wales_end_1, wales_start_2, wales_end_2, ...]
// Each boundary is delta-encoded (successive differences) to keep small
// numbers small. Postcodes are treated as base-36 integers for comparison.
// Lookup: find the insertion point with binary search. An odd index means
// the postcode sits inside a [start, end) Wales range; even means it doesn't.
(function () {
  var WALES_BOUNDS_DELTA = [750836674,3359232,4475,2,10,22,2007,3,8,9,3,49,3,1,33,2,105,3,4,2,316,2,1,3,9,1,131599,153,2,638,3,1,31,43,8,21,1,2,10,2,1,3,9490,1,13,1,124,76,140,1,5,5,5,2,1,1,2,1,49,3,4,4,62,2,21,1,12,2,1,16,2,1,1,12,1,2,1,176,13,2,489,3,4,9,1,41,1,53,19,14,6,2,49,14,3,4,15,14,288,23,265,18,35442,57025,2,8115549,310866336,11,2,24,5,11,1,7,47,49,3,1,19,4,93,322,17,1,2,6,2,1,1,5,1,32,3,1,2,11,7,114,2,5,2,1,1,12,25,11,1,2,1,1,1,3,2,2,2,1,4,2,36,374,58,1,108,2,83,4,7,4,66,2,9,5,521,1,88712,1,59,4,248,1,11,1,1,56,2,2,4,7,1,1,4,1,14,23,1,12,218165435,274884,13,324,5,223,29,16,2,138,1,1,2,2,36,47287,16,1,120,4,1,1,3,2,65,85,2,1351169,139406832,191097,7,26,19,28,5,1,2,1026,70,49429,1,641,475,2,14,53,37040,10513,2,112,4,3069961,273777408,6717168,33779277,6,11342,42,105,2,15,2,2,31,13,2,2,6,160,1,249,143,91782,4,89201,1,6,1,25279474981,122855616,139971,1,46652,1,1446350,1,6530529,2,2,1,8939196823,2,10883667489,146504,2,2,1,47,7,4,40,7,2,237,2,4,1,120745442,4474490418,289008,491,53,252,69,6,2,6,21,18,1627523,5,1,1,642,1,380,1,3,13,3,73,1389,7,23,1,2,19,119016227,9855990576,241856928,1209323520,792,1,6,8601,11,1,1,5,1,65,1,59,2,12,4,1,50,5,1,11,4,1,49,2031,1,6,1,134,3,81,47,6,10,1,73,1,1,2,1,121,1,130686,4,2,11,1,52,2,12,1,5,120,2,756,38,1,5,5,128,61,86,166,52,248,2,1,1,1115,1,49940,2,10,11,240,1,54,2,10,3,3,123,44888,23,2,14,2,1,73,8,1,1,1,21,2,10,1,111,3,2,1,386,1,8,12,17,5,3,11,1,8,7,1,164,39275,1455527,4,36,1,1,1,4,1,18,1,149,14,43214,12,633,13,3,1,92,3,91,1,54,1,4,4,1,1,3,52,132,1,96,2,94,2,1,1,2,29,12085668];

  function expand(deltas) {
    var out = new Array(deltas.length);
    var last = 0;
    for (var i = 0; i < deltas.length; i++) {
      last += deltas[i];
      out[i] = last;
    }
    return out;
  }

  function bisectLeft(arr, val) {
    var lo = 0, hi = arr.length;
    while (lo < hi) {
      var mid = (lo + hi) >> 1;
      if (arr[mid] < val) lo = mid + 1;
      else hi = mid;
    }
    return lo;
  }

  var walesBounds = expand(WALES_BOUNDS_DELTA);
  var postcodeRegex = /^(?:[A-Z]{2}[0-9][A-Z]|[A-Z][0-9][A-Z]|[A-Z][0-9]|[A-Z][0-9]{2}|[A-Z]{2}[0-9]|[A-Z]{2}[0-9]{2})[0-9][A-Z]{2}$/;

  function isWales(postcode) {
    if (typeof postcode !== "string") return false;
    var normalised = postcode.replace(/\s/g, "").toUpperCase();
    if (!postcodeRegex.test(normalised)) return false;
    var n = parseInt(normalised, 36);
    return bisectLeft(walesBounds, n) % 2 === 1;
  }

  var input = document.getElementById("pc");
  var welshGo = document.getElementById("welsh-go");

  if (input && welshGo) {
    // Derive the Welsh host from the current one: strip a leading `www.`, then
    // prefix `cy.` unless we are somehow already on it.
    var host = location.hostname.replace(/^www\./, "");
    var welshHost = host.indexOf("cy.") === 0 ? host : "cy." + host;

    var update = function () {
      var pc = input.value.trim();
      if (isWales(pc)) {
        welshGo.href =
          location.protocol + "//" + welshHost + "/?pc=" + encodeURIComponent(pc);
        // Toggle inline style rather than the `hidden` attribute: the `.button`
        // class sets `display`, which would otherwise override `[hidden]`.
        welshGo.style.display = "";
      } else {
        welshGo.style.display = "none";
      }
    };

    input.addEventListener("input", update);
    update();
  }
})();
