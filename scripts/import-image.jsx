var CM_TO_POINT = 28.346456692913385;
var POINT_TO_CM = 1 / CM_TO_POINT;
var MASK_SIZE_CM = 30.48;
var MASK_SIZE_POINT = MASK_SIZE_CM * CM_TO_POINT;
var EQUAL_TOLERANCE_CM = 0.02;
var PACK_MARGIN_CM = 2;
var PACK_GAP_CM = 0.5;
var PACK_MARGIN_POINT = PACK_MARGIN_CM * CM_TO_POINT;
var PACK_GAP_POINT = PACK_GAP_CM * CM_TO_POINT;
var CODEX_REPORTS = [];

function ptToCm(value) { return Math.round(value * POINT_TO_CM * 1000) / 1000; }
function addReport(message) { try { CODEX_REPORTS.push(message); } catch (error) {} }

function getOrOpenTemplate(templatePath) {
  var docs = app.documents;
  var normalized = String(templatePath).replace(/\\/g, '/').toLowerCase();
  for (var i = 0; i < docs.length; i += 1) {
    try {
      if (String(docs[i].fullName.fsName).replace(/\\/g, '/').toLowerCase() === normalized) {
        app.activeDocument = docs[i];
        return docs[i];
      }
    } catch (error) {}
  }
  return app.open(new File(templatePath));
}

function unlockAndShow(item) {
  try { item.locked = false; } catch (error) {}
  try { item.hidden = false; } catch (error) {}
  try { item.visible = true; } catch (error) {}
}

function ensureLayer(documentRef, layerName) {
  for (var i = 0; i < documentRef.layers.length; i += 1) {
    unlockAndShow(documentRef.layers[i]);
    if (documentRef.layers[i].name === layerName) return documentRef.layers[i];
  }
  var layer = documentRef.layers.add();
  layer.name = layerName;
  unlockAndShow(layer);
  return layer;
}

function removeSublayer(parentLayer, name) {
  for (var i = parentLayer.layers.length - 1; i >= 0; i -= 1) {
    try {
      if (parentLayer.layers[i].name === name) {
        unlockAndShow(parentLayer.layers[i]);
        parentLayer.layers[i].remove();
      }
    } catch (error) {}
  }
}

function caseLayerName(label) {
  if (typeof CODEX_IMAGE_BASENAME !== 'undefined' && CODEX_IMAGE_BASENAME) return CODEX_IMAGE_BASENAME + '_' + label;
  return label;
}

function lazerOutlineName() {
  if (typeof CODEX_IMAGE_ID !== 'undefined' && CODEX_IMAGE_ID) return CODEX_IMAGE_ID + '_DEBUG_LAZER';
  return 'DEBUG_LAZER_OUTLINE_AFTER_SCALE';
}

function longestEdgesLayerName() {
  if (typeof CODEX_IMAGE_ID !== 'undefined' && CODEX_IMAGE_ID) return CODEX_IMAGE_ID + '_DEBUG_LONGEST_EDGES';
  return 'DEBUG_LONGEST_EDGES';
}

function caseLabelFromLayerName(layerName) {
  var text = String(layerName);
  if (text === 'lazer' || text === 'front' || text === 'back') return text;
  if (text.lastIndexOf('_lazer') === text.length - 6) return 'lazer';
  if (text.lastIndexOf('_front') === text.length - 6) return 'front';
  if (text.lastIndexOf('_back') === text.length - 5) return 'back';
  return text;
}

function isCaseLayerName(layerName, label) {
  return caseLabelFromLayerName(layerName) === label;
}

function createCaseLayer(parentLayer, label) {
  removeSublayer(parentLayer, label);
  removeSublayer(parentLayer, caseLayerName(label));
  removeNamedPageItems(parentLayer, caseLayerName(label));
  removeNamedPageItems(parentLayer, 'MASK_30_48CM_' + label);
  removeNamedPageItems(parentLayer, 'IMAGE_' + label);
  removeNamedPageItems(parentLayer, 'DEBUG_BLACK_PIXEL_BOUNDS_' + label);
  unlockAndShow(parentLayer);
  return parentLayer;
}

function boundsOf(item) {
  var b = item.visibleBounds;
  return { left: b[0], top: b[1], right: b[2], bottom: b[3], width: b[2] - b[0], height: b[1] - b[3] };
}

function createMask(caseLayer, documentRef, label, offsetIndex) {
  documentRef.activeLayer = caseLayer;
  var left = 50 + (offsetIndex * (MASK_SIZE_POINT + 40));
  var top = documentRef.height - 50;
  var mask = caseLayer.pathItems.rectangle(top, left, MASK_SIZE_POINT, MASK_SIZE_POINT);
  mask.name = 'MASK_30_48CM_' + label;
  mask.filled = false;
  mask.stroked = true;
  unlockAndShow(mask);
  return mask;
}

function findNamedRaster(container, name) {
  for (var i = container.pageItems.length - 1; i >= 0; i -= 1) {
    try {
      if (container.pageItems[i].typename === 'RasterItem' && container.pageItems[i].name === name) return container.pageItems[i];
    } catch (error) {}
  }
  return null;
}

function cleanupLooseNamedItems(container, name, keepItem) {
  for (var i = container.pageItems.length - 1; i >= 0; i -= 1) {
    var item = null;
    try { item = container.pageItems[i]; } catch (error) { continue; }
    try {
      if (item !== keepItem && item.name === name && item.parent === container) {
        unlockAndShow(item);
        item.remove();
      }
    } catch (error) {}
  }
}

function embedImage(caseLayer, documentRef, imagePath, label, offsetIndex) {
  documentRef.activeLayer = caseLayer;
  var placed = caseLayer.placedItems.add();
  placed.file = new File(imagePath);
  placed.position = [50 + (offsetIndex * (MASK_SIZE_POINT + 40)), documentRef.height - 50];
  try { placed.name = 'IMAGE_' + label; } catch (error) {}
  try { placed.embed(); } catch (error) {}
  var raster = findNamedRaster(caseLayer, 'IMAGE_' + label);
  if (raster === null && documentRef.rasterItems.length > 0) raster = documentRef.rasterItems[documentRef.rasterItems.length - 1];
  if (raster === null) return null;
  try { raster.name = 'IMAGE_' + label; } catch (error) {}
  unlockAndShow(raster);
  try { raster.move(caseLayer, ElementPlacement.PLACEATBEGINNING); } catch (error) {}
  cleanupLooseNamedItems(caseLayer, 'IMAGE_' + label, raster);
  return raster;
}

function alignImageToMask(raster, mask, verticalMode) {
  var rb = boundsOf(raster);
  var mb = boundsOf(mask);
  var dx = ((mb.left + mb.right) / 2) - ((rb.left + rb.right) / 2);
  var dy = 0;
  if (verticalMode === 'top') dy = mb.top - rb.top;
  else if (verticalMode === 'bottom') dy = mb.bottom - rb.bottom;
  else dy = ((mb.top + mb.bottom) / 2) - ((rb.top + rb.bottom) / 2);
  raster.translate(dx, dy);
}

function makeClip(caseLayer, raster, mask, label) {
  var group = caseLayer.groupItems.add();
  group.name = caseLayerName(label);
  unlockAndShow(group);

  raster.move(group, ElementPlacement.PLACEATEND);
  mask.move(group, ElementPlacement.PLACEATEND);
  cleanupLooseNamedItems(caseLayer, 'IMAGE_' + label, raster);
  try { mask.zOrder(ZOrderMethod.BRINGTOFRONT); } catch (error) {}
  mask.clipping = true;
  group.clipped = true;
  unlockAndShow(raster);
  unlockAndShow(mask);
  return group;
}

function findRasterInGroup(group) {
  for (var i = 0; i < group.pageItems.length; i += 1) {
    try { if (group.pageItems[i].typename === 'RasterItem') return group.pageItems[i]; } catch (error) {}
  }
  return null;
}

function unionBounds(boundsList) {
  if (boundsList.length === 0) return null;
  var left = boundsList[0].left;
  var top = boundsList[0].top;
  var right = boundsList[0].right;
  var bottom = boundsList[0].bottom;
  for (var i = 1; i < boundsList.length; i += 1) {
    if (boundsList[i].left < left) left = boundsList[i].left;
    if (boundsList[i].top > top) top = boundsList[i].top;
    if (boundsList[i].right > right) right = boundsList[i].right;
    if (boundsList[i].bottom < bottom) bottom = boundsList[i].bottom;
  }
  return { left: left, top: top, right: right, bottom: bottom, width: right - left, height: top - bottom };
}
function allColoredBounds(raster, metrics) {
  var list = [];
  if (!metrics || !metrics.components) return list;
  var rb = boundsOf(raster);
  var sx = rb.width / metrics.imageWidthPx;
  var sy = rb.height / metrics.imageHeightPx;
  for (var i = 0; i < metrics.components.length; i += 1) {
    var c = metrics.components[i];
    list.push({ index: i, bounds: {
      left: rb.left + (c.minX * sx),
      right: rb.left + ((c.maxX + 1) * sx),
      top: rb.top - (c.minY * sy),
      bottom: rb.top - ((c.maxY + 1) * sy),
      width: (c.maxX - c.minX + 1) * sx,
      height: (c.maxY - c.minY + 1) * sy
    }});
  }
  return list;
}

function insideMask(mask, bounds) {
  var mb = boundsOf(mask);
  return bounds.left >= mb.left && bounds.top <= mb.top && bounds.right <= mb.right && bounds.bottom >= mb.bottom;
}

function pickBounds(mask, boundsList, preferredIndex, useUnion) {
  var inside = [];
  for (var i = 0; i < boundsList.length; i += 1) if (insideMask(mask, boundsList[i].bounds)) inside.push(boundsList[i].bounds);
  if (inside.length === 0) return null;
  if (useUnion === true) return unionBounds(inside);
  for (var j = 0; j < inside.length; j += 1) if (boundsList[j].index === preferredIndex) return inside[j];
  return inside[Math.floor(inside.length / 2)];
}

function blue() {
  var color = new RGBColor(); color.red = 0; color.green = 102; color.blue = 255; return color;
}

function drawDebug(caseLayer, bounds, label) {
  if (bounds === null) return null;
  var box = caseLayer.pathItems.rectangle(bounds.top, bounds.left, bounds.width, bounds.height);
  box.name = 'DEBUG_BLACK_PIXEL_BOUNDS_' + label;
  box.filled = false;
  box.stroked = true;
  box.strokeWidth = 0.25;
  box.strokeColor = blue();
  unlockAndShow(box);
  return box;
}

function report(label, mask, bounds) {
  if (bounds === null) return label + '\nfalse\nKhong tim thay o mau trong mask';
  var mb = boundsOf(mask);
  var leftCm = ptToCm(bounds.left - mb.left);
  var topCm = ptToCm(mb.top - bounds.top);
  var rightCm = ptToCm(mb.right - bounds.right);
  var bottomCm = ptToCm(bounds.bottom - mb.bottom);
  var ok = Math.abs(leftCm - rightCm) <= EQUAL_TOLERANCE_CM && Math.abs(topCm - bottomCm) <= EQUAL_TOLERANCE_CM;
  return [(ok ? 'true' : 'false'), label, 'Trai: ' + leftCm + ' cm', 'Tren: ' + topCm + ' cm', 'Phai: ' + rightCm + ' cm', 'Duoi: ' + bottomCm + ' cm'].join('\n');
}

function findDebugBounds(caseLayer, label) {
  var found = findNamedPageItem(caseLayer, 'DEBUG_BLACK_PIXEL_BOUNDS_' + label);
  if (found !== null) return found;
  return null;
}

function removeDebugFromContainer(container, debugName) {
  for (var i = container.pageItems.length - 1; i >= 0; i -= 1) {
    try {
      var item = container.pageItems[i];
      if (item.typename === 'GroupItem') removeDebugFromContainer(item, debugName);
      if (item.name === debugName) {
        unlockAndShow(item);
        item.remove();
      }
    } catch (error) {}
  }
}

function removeDebugByLabel(parentLayer, label) {
  var debugName = 'DEBUG_BLACK_PIXEL_BOUNDS_' + label;
  removeDebugFromContainer(parentLayer, debugName);
}

function collectRootScaleItems(parentLayer) {
  var items = [];
  unlockAndShow(parentLayer);
  for (var g = 0; g < parentLayer.groupItems.length; g += 1) {
    try { if (parentLayer.groupItems[g].parent === parentLayer) { unlockAndShow(parentLayer.groupItems[g]); items.push(parentLayer.groupItems[g]); } } catch (error) {}
  }
  for (var p = 0; p < parentLayer.pathItems.length; p += 1) {
    try { if (parentLayer.pathItems[p].parent === parentLayer) { unlockAndShow(parentLayer.pathItems[p]); items.push(parentLayer.pathItems[p]); } } catch (error) {}
  }
  return items;
}

function createTempScaleGroup(documentRef, parentLayer) {
  var items = collectRootScaleItems(parentLayer);
  if (items.length === 0) return null;
  documentRef.selection = null;
  for (var i = 0; i < items.length; i += 1) {
    try { items[i].selected = true; } catch (error) {}
  }
  try { app.executeMenuCommand('group'); } catch (error) {}
  var group = null;
  if (documentRef.selection !== null && documentRef.selection.length > 0) {
    try { group = documentRef.selection[0]; } catch (error) {}
  }
  if (group !== null) {
    try { group.name = 'TEMP_SCALE_GROUP_IMAGES'; } catch (error) {}
    try { group.move(parentLayer, ElementPlacement.PLACEATBEGINNING); } catch (error) {}
  }
  return group;
}

function ungroupTempScaleGroup(parentLayer) {
  ungroupTempAlignGroup(parentLayer, 'TEMP_SCALE_GROUP_IMAGES');
}

function scaleRootItemsTogether(items, scalePercent) {
  var groupBounds = unionVisibleBounds(items);
  if (groupBounds === null) return;
  var centerX = (groupBounds.left + groupBounds.right) / 2;
  var centerY = (groupBounds.top + groupBounds.bottom) / 2;
  var ratio = scalePercent / 100;
  for (var i = 0; i < items.length; i += 1) {
    try {
      var before = boundsOf(items[i]);
      var beforeCenterX = (before.left + before.right) / 2;
      var beforeCenterY = (before.top + before.bottom) / 2;
      items[i].resize(scalePercent, scalePercent, true, true, true, true, scalePercent, Transformation.CENTER);
      var after = boundsOf(items[i]);
      var afterCenterX = (after.left + after.right) / 2;
      var afterCenterY = (after.top + after.bottom) / 2;
      var targetCenterX = centerX + ((beforeCenterX - centerX) * ratio);
      var targetCenterY = centerY + ((beforeCenterY - centerY) * ratio);
      items[i].translate(targetCenterX - afterCenterX, targetCenterY - afterCenterY);
    } catch (error) {}
  }
}

function scaleImagesByLazerSize(documentRef, parentLayer) {
  if (typeof CODEX_ITEM_SIZE_INCH === 'undefined' || CODEX_ITEM_SIZE_INCH <= 0) return;
  var debugItem = findDebugBounds(parentLayer, 'lazer');
  if (debugItem === null) {
    addReport('Scale failed: cannot find DEBUG_BLACK_PIXEL_BOUNDS_lazer');
    return;
  }
  var beforeBounds = boundsOf(debugItem);
  var currentSize = beforeBounds.width > beforeBounds.height ? beforeBounds.width : beforeBounds.height;
  var targetSize = CODEX_ITEM_SIZE_INCH * 72;
  if (currentSize <= 0 || targetSize <= 0) return;
  var scalePercent = (targetSize / currentSize) * 100;
  var items = collectRootScaleItems(parentLayer);
  scaleRootItemsTogether(items, scalePercent);
  var afterBounds = boundsOf(debugItem);
  var afterSize = afterBounds.width > afterBounds.height ? afterBounds.width : afterBounds.height;
  addReport([
    'Scale by DEBUG_BLACK_PIXEL_BOUNDS_lazer',
    'Before: ' + (currentSize / 72) + ' in',
    'Target: ' + CODEX_ITEM_SIZE_INCH + ' in',
    'Scale: ' + scalePercent + '%',
    'After: ' + (afterSize / 72) + ' in',
    'OK: ' + (Math.abs(afterSize - targetSize) <= 0.5)
  ].join('\n'));
}
function selectImagesForScaling(documentRef, parentLayer) {
  documentRef.selection = null;
}
function removeDebugBounds(parentLayer) {
  for (var i = 0; i < parentLayer.layers.length; i += 1) {
    var caseLayer = parentLayer.layers[i];
    unlockAndShow(caseLayer);
    for (var j = caseLayer.pathItems.length - 1; j >= 0; j -= 1) {
      try {
        var pathItem = caseLayer.pathItems[j];
        if (pathItem.name.indexOf('DEBUG_BLACK_PIXEL_BOUNDS_') === 0) {
          unlockAndShow(pathItem);
          pathItem.remove();
        }
      } catch (error) {}
    }
  }
}

function collectLayerPageItems(layerRef, list) {
  for (var i = 0; i < layerRef.pageItems.length; i += 1) {
    try {
      unlockAndShow(layerRef.pageItems[i]);
      list.push(layerRef.pageItems[i]);
    } catch (error) {}
  }
  for (var j = 0; j < layerRef.layers.length; j += 1) collectLayerPageItems(layerRef.layers[j], list);
}

function unionVisibleBounds(items) {
  var list = [];
  for (var i = 0; i < items.length; i += 1) {
    try {
      var b = items[i].visibleBounds;
      list.push({ left: b[0], top: b[1], right: b[2], bottom: b[3] });
    } catch (error) {}
  }
  if (list.length === 0) return null;
  var left = list[0].left;
  var top = list[0].top;
  var right = list[0].right;
  var bottom = list[0].bottom;
  for (var j = 1; j < list.length; j += 1) {
    if (list[j].left < left) left = list[j].left;
    if (list[j].top > top) top = list[j].top;
    if (list[j].right > right) right = list[j].right;
    if (list[j].bottom < bottom) bottom = list[j].bottom;
  }
  return { left: left, top: top, right: right, bottom: bottom };
}

function unionLayerPageItemBounds(layerRef) {
  unlockAndShow(layerRef);
  var list = [];
  for (var i = 0; i < layerRef.pageItems.length; i += 1) {
    try {
      var item = layerRef.pageItems[i];
      if (item.name && item.name.indexOf('DEBUG_BLACK_PIXEL_BOUNDS_') === 0) continue;
      var b = item.visibleBounds;
      list.push({ left: b[0], top: b[1], right: b[2], bottom: b[3] });
    } catch (error) {}
  }
  if (list.length === 0) return null;
  var left = list[0].left;
  var top = list[0].top;
  var right = list[0].right;
  var bottom = list[0].bottom;
  for (var j = 1; j < list.length; j += 1) {
    if (list[j].left < left) left = list[j].left;
    if (list[j].top > top) top = list[j].top;
    if (list[j].right > right) right = list[j].right;
    if (list[j].bottom < bottom) bottom = list[j].bottom;
  }
  return { left: left, top: top, right: right, bottom: bottom, width: right - left, height: top - bottom };
}

function lineLikeBounds(item) {
  try {
    var b = item.visibleBounds;
    var width = b[2] - b[0];
    var height = b[1] - b[3];
    return width <= 3 || height <= 3;
  } catch (error) {}
  return false;
}

function getMarginBoxFromFourEdges(layerRef) {
  var leftEdge = null;
  var rightEdge = null;
  var topEdge = null;
  var bottomEdge = null;
  for (var i = 0; i < layerRef.pageItems.length; i += 1) {
    try {
      var item = layerRef.pageItems[i];
      if (!lineLikeBounds(item)) continue;
      var b = boundsOf(item);
      if (b.width <= 3) {
        var centerX = (b.left + b.right) / 2;
        if (leftEdge === null || centerX < leftEdge.value) leftEdge = { value: centerX, item: item };
        if (rightEdge === null || centerX > rightEdge.value) rightEdge = { value: centerX, item: item };
      }
      if (b.height <= 3) {
        var centerY = (b.top + b.bottom) / 2;
        if (topEdge === null || centerY > topEdge.value) topEdge = { value: centerY, item: item };
        if (bottomEdge === null || centerY < bottomEdge.value) bottomEdge = { value: centerY, item: item };
      }
    } catch (error) {}
  }
  if (leftEdge === null || rightEdge === null || topEdge === null || bottomEdge === null) return null;
  return {
    left: leftEdge.value,
    top: topEdge.value,
    right: rightEdge.value,
    bottom: bottomEdge.value,
    source: 'Layer Margin 4 edges'
  };
}

function findTemplateItemInMargin(layerRef) {
  for (var i = 0; i < layerRef.pageItems.length; i += 1) {
    try {
      var item = layerRef.pageItems[i];
      if (String(item.name).toLowerCase().indexOf('template') >= 0) return item;
    } catch (error) {}
  }
  return null;
}

function collectSelectablePageItems(container, list, skipDebugBounds) {
  if (container.name && (String(container.name).toLowerCase() === 'margin' || String(container.name).toLowerCase() === 'border')) return;
  for (var i = 0; i < container.pageItems.length; i += 1) {
    try {
      var item = container.pageItems[i];
      unlockAndShow(item);
      if (skipDebugBounds && item.name && item.name.indexOf('DEBUG_BLACK_PIXEL_BOUNDS_') === 0) continue;
      list.push(item);
    } catch (error) {}
  }
  if (container.layers) {
    for (var j = 0; j < container.layers.length; j += 1) {
      try { collectSelectablePageItems(container.layers[j], list, skipDebugBounds); } catch (error) {}
    }
  }
}

function groupContainerItems(container, groupName, skipDebugBounds) {
  var items = [];
  collectSelectablePageItems(container, items, skipDebugBounds);
  if (items.length === 0) return null;
  var documentRef = app.activeDocument;
  documentRef.selection = null;
  for (var j = 0; j < items.length; j += 1) {
    try { items[j].selected = true; } catch (error) {}
  }
  try { app.executeMenuCommand('group'); } catch (error) {}
  var group = null;
  if (documentRef.selection !== null && documentRef.selection.length > 0) {
    try { group = documentRef.selection[0]; } catch (error) {}
  }
  if (group !== null) {
    try { group.name = groupName; } catch (error) {}
  }
  return group;
}

function ungroupCaseItems(container, groupName) {
  for (var i = container.groupItems.length - 1; i >= 0; i -= 1) {
    try {
      var group = container.groupItems[i];
      if (group.name !== groupName) {
        ungroupCaseItems(group, groupName);
        continue;
      }
      unlockAndShow(group);
      while (group.pageItems.length > 0) {
        try { group.pageItems[0].move(container, ElementPlacement.PLACEATEND); } catch (error) { break; }
      }
      group.remove();
    } catch (error) {}
  }
}

function isMarginName(name) {
  if (!name) return false;
  return String(name).toLowerCase().indexOf('margin') >= 0;
}

function findLayerByName(container, layerName) {
  if (!container || !container.layers) return null;
  for (var i = 0; i < container.layers.length; i += 1) {
    try {
      var layer = container.layers[i];
      if (String(layer.name).toLowerCase() === String(layerName).toLowerCase()) return layer;
      var nested = findLayerByName(layer, layerName);
      if (nested !== null) return nested;
    } catch (error) {}
  }
  return null;
}

function collectMarginItems(container, list) {
  for (var i = 0; i < container.pageItems.length; i += 1) {
    try {
      var item = container.pageItems[i];
      if (isMarginName(item.name) || isMarginName(item.parent && item.parent.name)) list.push(item);
      if (item.typename === 'GroupItem') collectMarginItems(item, list);
    } catch (error) {}
  }
  for (var j = 0; j < container.layers.length; j += 1) {
    try { collectMarginItems(container.layers[j], list); } catch (error) {}
  }
}

function findPackAreaBounds(documentRef) {
  var marginLayer = findLayerByName(documentRef, 'Margin');
  if (marginLayer !== null) {
    unlockAndShow(marginLayer);
    var templateItem = findTemplateItemInMargin(marginLayer);
    if (templateItem !== null) {
      var templateBounds = boundsOf(templateItem);
      return {
        left: templateBounds.left,
        top: templateBounds.top,
        right: templateBounds.right,
        bottom: templateBounds.bottom,
        source: 'Margin Template'
      };
    }
    var edgeBox = getMarginBoxFromFourEdges(marginLayer);
    if (edgeBox !== null) return edgeBox;
    var layerBounds = unionLayerPageItemBounds(marginLayer);
    if (layerBounds !== null) {
      return {
        left: layerBounds.left,
        top: layerBounds.top,
        right: layerBounds.right,
        bottom: layerBounds.bottom,
        source: 'Layer Margin'
      };
    }
  }

  var marginItems = [];
  collectMarginItems(documentRef, marginItems);
  var marginBounds = unionVisibleBounds(marginItems);
  if (marginBounds !== null) {
    return {
      left: marginBounds.left,
      top: marginBounds.top,
      right: marginBounds.right,
      bottom: marginBounds.bottom,
      source: 'Margin'
    };
  }
  return {
    left: PACK_MARGIN_POINT,
    top: documentRef.height - PACK_MARGIN_POINT,
    right: documentRef.width - PACK_MARGIN_POINT,
    bottom: PACK_MARGIN_POINT,
    source: 'Fallback 2cm'
  };
}

function alignLayerItemsToBounds(layerRef, targetBounds) {
  var sourceBounds = unionLayerPageItemBounds(layerRef);
  if (sourceBounds === null) return;
  var dx = ((targetBounds.left + targetBounds.right) / 2) - ((sourceBounds.left + sourceBounds.right) / 2);
  var dy = ((targetBounds.top + targetBounds.bottom) / 2) - ((sourceBounds.top + sourceBounds.bottom) / 2);
  translateLayerPageItems(layerRef, dx, dy);
}

function translateLayerPageItems(layerRef, dx, dy) {
  for (var i = 0; i < layerRef.pageItems.length; i += 1) {
    try {
      unlockAndShow(layerRef.pageItems[i]);
      layerRef.pageItems[i].translate(dx, dy);
    } catch (error) {}
  }
}

function translateCaseItems(parentLayer, label, dx, dy) {
  var clip = findNamedPageItem(parentLayer, caseLayerName(label));
  var debug = findNamedPageItem(parentLayer, 'DEBUG_BLACK_PIXEL_BOUNDS_' + label);
  var outline = label === 'lazer' ? findNamedPageItem(parentLayer, lazerOutlineName()) : null;
  try { if (clip !== null) clip.translate(dx, dy); } catch (error) {}
  try { if (debug !== null) debug.translate(dx, dy); } catch (error) {}
  try { if (outline !== null) outline.translate(dx, dy); } catch (error) {}
}

function rotateLayerPageItemsAroundCenter(layerRef, angle) {
  var box = unionLayerPageItemBounds(layerRef);
  if (box === null) return;
  var centerX = (box.left + box.right) / 2;
  var centerY = (box.top + box.bottom) / 2;
  for (var i = 0; i < layerRef.pageItems.length; i += 1) {
    try {
      var item = layerRef.pageItems[i];
      unlockAndShow(item);
      item.rotate(angle, true, true, true, true, Transformation.CENTER);
    } catch (error) {}
  }
  var after = unionLayerPageItemBounds(layerRef);
  if (after === null) return;
  var afterCenterX = (after.left + after.right) / 2;
  var afterCenterY = (after.top + after.bottom) / 2;
  translateLayerPageItems(layerRef, centerX - afterCenterX, centerY - afterCenterY);
}

function optimizeLayerRotationForPacking(layerRef) {
  var before = null;
  var outline = null;
  try { outline = findNamedPageItem(layerRef, lazerOutlineName()); } catch (error) {}
  if (outline !== null) before = boundsOf(outline);
  if (before === null) before = unionLayerPageItemBounds(layerRef);
  if (before === null) return;
  if (before.height >= before.width) return;
  rotateLayerPageItemsAroundCenter(layerRef, 90);
}

function optimizeCaseRotationForPacking(parentLayer, label) {
  var outline = label === 'lazer' ? findNamedPageItem(parentLayer, lazerOutlineName()) : null;
  var debug = findNamedPageItem(parentLayer, 'DEBUG_BLACK_PIXEL_BOUNDS_' + label);
  var ref = outline !== null ? outline : debug;
  if (ref === null) return;
  var rotateAngle = 0;
  var b = boundsOf(ref);
  if (b.height >= b.width) return;
  rotateAngle = 90;
  if (Math.abs(rotateAngle) < 0.01) return;
  var clip = findNamedPageItem(parentLayer, caseLayerName(label));
  var items = [];
  if (clip !== null) items.push(clip);
  if (debug !== null) items.push(debug);
  if (outline !== null) items.push(outline);
  var union = unionVisibleBounds(items);
  if (union === null) return;
  var centerX = (union.left + union.right) / 2;
  var centerY = (union.top + union.bottom) / 2;
  for (var i = 0; i < items.length; i += 1) {
    try { items[i].rotate(rotateAngle, true, true, true, true, Transformation.CENTER); } catch (error) {}
  }
  var after = unionVisibleBounds(items);
  if (after === null) return;
  translateCaseItems(parentLayer, label, centerX - ((after.left + after.right) / 2), centerY - ((after.top + after.bottom) / 2));
}

function findNamedPageItem(container, itemName) {
  for (var i = 0; i < container.pageItems.length; i += 1) {
    try {
      var item = container.pageItems[i];
      if (item.name === itemName) return item;
      if (item.typename === 'GroupItem') {
        var nested = findNamedPageItem(item, itemName);
        if (nested !== null) return nested;
      }
    } catch (error) {}
  }
  return null;
}

function red() {
  var color = new RGBColor(); color.red = 220; color.green = 40; color.blue = 40; return color;
}

function getLongestOutlineEdges(outline) {
  var edges = [];
  try {
    var points = outline.pathPoints;
    if (!points || points.length < 2) return edges;
    for (var i = 0; i < points.length; i += 1) {
      var nextIndex = i === points.length - 1 ? 0 : i + 1;
      var a = points[i].anchor;
      var b = points[nextIndex].anchor;
      var dx = b[0] - a[0];
      var dy = b[1] - a[1];
      var length = Math.sqrt((dx * dx) + (dy * dy));
      if (length > 0.01) edges.push({ a: a, b: b, length: length });
    }
  } catch (error) {}
  if (edges.length === 0) return edges;
  edges.sort(function(left, right) { return right.length - left.length; });
  var maxLength = edges[0].length;
  var threshold = maxLength - 0.5;
  var longest = [];
  for (var j = 0; j < edges.length; j += 1) {
    if (edges[j].length >= threshold) longest.push(edges[j]);
  }
  return longest;
}

function normalizeAngle(angle) {
  while (angle > 90) angle -= 180;
  while (angle < -90) angle += 180;
  return angle;
}

function edgeAngle(edge) {
  return normalizeAngle(Math.atan2(edge.b[1] - edge.a[1], edge.b[0] - edge.a[0]) * 180 / Math.PI);
}

function removeDocumentLayer(documentRef, layerName) {
  for (var i = documentRef.layers.length - 1; i >= 0; i -= 1) {
    try {
      if (documentRef.layers[i].name === layerName) {
        unlockAndShow(documentRef.layers[i]);
        documentRef.layers[i].remove();
      }
    } catch (error) {}
  }
}

function edgeAlignmentError(edge) {
  var angle = Math.abs(edgeAngle(edge));
  var horizontalError = Math.abs(angle - 0);
  var verticalError = Math.abs(angle - 90);
  if (verticalError < horizontalError) return verticalError;
  return horizontalError;
}

function edgeBounds(edge) {
  var left = edge.a[0] < edge.b[0] ? edge.a[0] : edge.b[0];
  var right = edge.a[0] > edge.b[0] ? edge.a[0] : edge.b[0];
  var top = edge.a[1] > edge.b[1] ? edge.a[1] : edge.b[1];
  var bottom = edge.a[1] < edge.b[1] ? edge.a[1] : edge.b[1];
  return { left: left, right: right, top: top, bottom: bottom };
}

function deltaToPlaceBoundsInsideTemplate(bounds, templateBounds) {
  var dx = templateBounds.left - bounds.left;
  var dy = templateBounds.top - bounds.top;
  var movedRight = bounds.right + dx;
  var movedBottom = bounds.bottom + dy;
  if (movedRight > templateBounds.right) dx = templateBounds.right - bounds.right;
  if (movedBottom < templateBounds.bottom) dy = templateBounds.bottom - bounds.bottom;
  return { dx: dx, dy: dy };
}

function movedBounds(bounds, dx, dy) {
  return { left: bounds.left + dx, right: bounds.right + dx, top: bounds.top + dy, bottom: bounds.bottom + dy, width: bounds.width, height: bounds.height };
}

function boundsInsideTemplate(bounds, templateBounds) {
  var tolerance = 1.5;
  return bounds.left >= templateBounds.left - tolerance && bounds.right <= templateBounds.right + tolerance && bounds.top <= templateBounds.top + tolerance && bounds.bottom >= templateBounds.bottom - tolerance;
}

function countTemplateTouches(bounds, templateBounds) {
  var tolerance = 1.5;
  var touch = 0;
  if (Math.abs(bounds.left - templateBounds.left) <= tolerance) touch += 1;
  if (Math.abs(bounds.right - templateBounds.right) <= tolerance) touch += 1;
  if (Math.abs(bounds.top - templateBounds.top) <= tolerance) touch += 1;
  if (Math.abs(bounds.bottom - templateBounds.bottom) <= tolerance) touch += 1;
  return touch;
}



function expandBounds(bounds, padding) {
  return {
    left: bounds.left - padding,
    right: bounds.right + padding,
    top: bounds.top + padding,
    bottom: bounds.bottom - padding,
    width: (bounds.right - bounds.left) + (padding * 2),
    height: (bounds.top - bounds.bottom) + (padding * 2)
  };
}

function boundsIntersects(a, b) {
  return !(a.right <= b.left || a.left >= b.right || a.top <= b.bottom || a.bottom >= b.top);
}

function collectVisibleBounds(container) {
  var list = [];
  if (container === null) return list;
  for (var i = 0; i < container.pageItems.length; i += 1) {
    try { list.push(boundsOf(container.pageItems[i])); } catch (error) {}
  }
  return list;
}

function collidesWithAny(bounds, obstacles) {
  for (var i = 0; i < obstacles.length; i += 1) {
    if (boundsIntersects(bounds, obstacles[i])) return true;
  }
  return false;
}

function findFreePlacementInsideTemplate(bounds, templateBounds, obstacles) {
  try {
    if (bounds === null || templateBounds === null) return null;
    var width = bounds.right - bounds.left;
    var height = bounds.top - bounds.bottom;
    if (width <= 0 || height <= 0) return null;
    var step = Math.max(PACK_GAP_POINT, 8);
    var maxLeft = templateBounds.right - width;
    var maxTop = templateBounds.top;
    for (var y = maxTop; y >= templateBounds.bottom + height; y -= step) {
      for (var x = templateBounds.left; x <= maxLeft; x += step) {
        var placed = {
          left: x,
          right: x + width,
          top: y,
          bottom: y - height,
          width: width,
          height: height
        };
        if (!boundsInsideTemplate(placed, templateBounds)) continue;
        if (collidesWithAny(placed, obstacles)) continue;
        return { dx: placed.left - bounds.left, dy: placed.top - bounds.top, placedBounds: placed };
      }
    }
  } catch (error) {}
  return null;
}

function chooseDeltaForEdgeOnTemplate(edge, outlineBounds, templateBounds) {
  var edgeBox = edgeBounds(edge);
  var candidates = [
    { dx: templateBounds.left - edgeBox.left, dy: templateBounds.top - edgeBox.top, side: 'left-top' },
    { dx: templateBounds.left - edgeBox.left, dy: templateBounds.bottom - edgeBox.bottom, side: 'left-bottom' },
    { dx: templateBounds.left - outlineBounds.left, dy: templateBounds.top - edgeBox.top, side: 'bounds-left-edge-top' },
    { dx: templateBounds.left - outlineBounds.left, dy: templateBounds.bottom - edgeBox.bottom, side: 'bounds-left-edge-bottom' },
    { dx: templateBounds.left - edgeBox.left, dy: templateBounds.top - outlineBounds.top, side: 'edge-left-bounds-top' },
    { dx: templateBounds.left - edgeBox.left, dy: templateBounds.bottom - outlineBounds.bottom, side: 'edge-left-bounds-bottom' },
    { dx: templateBounds.right - edgeBox.right, dy: templateBounds.top - edgeBox.top, side: 'right-top' },
    { dx: templateBounds.right - edgeBox.right, dy: templateBounds.bottom - edgeBox.bottom, side: 'right-bottom' }
  ];
  var fallback = deltaToPlaceBoundsInsideTemplate(outlineBounds, templateBounds);
  var best = { dx: fallback.dx, dy: fallback.dy, score: -1, side: 'fallback-inside' };
  for (var i = 0; i < candidates.length; i += 1) {
    var moved = movedBounds(outlineBounds, candidates[i].dx, candidates[i].dy);
    var inside = boundsInsideTemplate(moved, templateBounds);
    if (!inside) continue;
    var touch = countTemplateTouches(moved, templateBounds);
    var edgeMoved = movedBounds(edgeBox, candidates[i].dx, candidates[i].dy);
    var edgeTouch = countTemplateTouches(edgeMoved, templateBounds);
    var leftBonus = Math.abs(edgeMoved.left - templateBounds.left) <= 1.5 ? 100 : 0;
    var rightPenalty = Math.abs(edgeMoved.right - templateBounds.right) <= 1.5 ? 0 : 0;
    var score = leftBonus + (edgeTouch * 10) + touch - rightPenalty;
    if (score > best.score) best = { dx: candidates[i].dx, dy: candidates[i].dy, score: score, side: candidates[i].side };
  }
  return best;
}

function outlineAnchorPoints(outline) {
  var points = [];
  try {
    for (var i = 0; i < outline.pathPoints.length; i += 1) {
      points.push([outline.pathPoints[i].anchor[0], outline.pathPoints[i].anchor[1]]);
    }
  } catch (error) {}
  return points;
}

function chooseFreeRotationForTemplate(outline, templateBounds) {
  var points = outlineAnchorPoints(outline);
  if (points.length < 2) return { angle: 0, bounds: boundsOf(outline), score: -999999 };
  var originalEdges = getLongestOutlineEdges(outline);
  var originalEdgeAngle = originalEdges.length > 0 ? edgeAngle(originalEdges[0]) : 0;
  var originalBounds = pointsBounds(points);
  var center = { x: (originalBounds.left + originalBounds.right) / 2, y: (originalBounds.top + originalBounds.bottom) / 2 };
  var templateWidth = templateBounds.right - templateBounds.left;
  var templateHeight = templateBounds.top - templateBounds.bottom;
  var best = { angle: 0, bounds: originalBounds, score: -999999 };
  for (var angle = 0; angle < 360; angle += 1) {
    var rotated = transformPoints(points, center, angle);
    var bounds = pointsBounds(rotated);
    var fits = bounds.width <= templateWidth + 1.5 && bounds.height <= templateHeight + 1.5;
    if (!fits) continue;
    var widthRatio = bounds.width / templateWidth;
    var heightRatio = bounds.height / templateHeight;
    var areaScore = (bounds.width * bounds.height) / (templateWidth * templateHeight);
    var placedBounds = movedBounds(bounds, templateBounds.left - bounds.left, templateBounds.top - bounds.top);
    if (placedBounds.right > templateBounds.right) placedBounds = movedBounds(bounds, templateBounds.right - bounds.right, templateBounds.top - bounds.top);
    if (placedBounds.bottom < templateBounds.bottom) placedBounds = movedBounds(bounds, placedBounds.left - bounds.left, templateBounds.bottom - bounds.bottom);
    var touchFlags = templateTouchFlags(placedBounds, templateBounds);
    var touch = 0;
    if (touchFlags.left) touch += 1;
    if (touchFlags.right) touch += 1;
    if (touchFlags.top) touch += 1;
    if (touchFlags.bottom) touch += 1;
    var leftToRightBonus = 1 - Math.abs(widthRatio - 0.75);
    var topToBottomBonus = 1 - Math.abs(heightRatio - 0.75);
    var edgeContactScore = 0;
    if (originalEdges.length > 0) {
      var rotatedLongestAngle = normalizeAngle(originalEdgeAngle + angle);
      var horizontalError = Math.abs(rotatedLongestAngle);
      var verticalError = Math.abs(90 - Math.abs(rotatedLongestAngle));
      var horizontalLike = horizontalError <= 10;
      var verticalLike = verticalError <= 10;
      if ((touchFlags.top || touchFlags.bottom) && horizontalLike) edgeContactScore += 2000 - (horizontalError * 50);
      if ((touchFlags.left || touchFlags.right) && verticalLike) edgeContactScore += 2000 - (verticalError * 50);
      if ((touchFlags.top || touchFlags.bottom) && !horizontalLike) edgeContactScore -= 1000;
      if ((touchFlags.left || touchFlags.right) && !verticalLike) edgeContactScore -= 1000;
    }
    var score = (touch * 1000) + edgeContactScore + (areaScore * 100) + (leftToRightBonus * 20) + (topToBottomBonus * 20);
    if (score > best.score) best = { angle: angle, bounds: bounds, score: score };
  }
  best.originalEdgeAngle = originalEdgeAngle;
  return best;
}

function chooseRotationByTemplateEdgeRule(outline, templateBounds) {
  var edges = getLongestOutlineEdges(outline);
  if (edges.length === 0) return 0;
  var best = { angle: 0, score: -999999 };
  var baseBounds = boundsOf(outline);
  var centerX = (baseBounds.left + baseBounds.right) / 2;
  var centerY = (baseBounds.top + baseBounds.bottom) / 2;
  for (var angle = 0; angle < 360; angle += 90) {
    var clone = outline.duplicate();
    try { clone.rotate(angle, true, true, true, true, Transformation.CENTER); } catch (error) {}
    var rotatedBounds = boundsOf(clone);
    var edge = getLongestOutlineEdges(clone);
    var score = 0;
    if (edge.length > 0) {
      var longest = edge[0];
      var edgeBox = edgeBounds(longest);
      var touchTopBottom = Math.abs(rotatedBounds.top - templateBounds.top) <= 1.5 || Math.abs(rotatedBounds.bottom - templateBounds.bottom) <= 1.5;
      var touchLeftRight = Math.abs(rotatedBounds.left - templateBounds.left) <= 1.5 || Math.abs(rotatedBounds.right - templateBounds.right) <= 1.5;
      var angleNow = Math.abs(normalizeAngle(edgeAngle(longest)));
      var nearHorizontal = Math.min(Math.abs(angleNow), Math.abs(180 - angleNow)) <= 10;
      var nearVertical = Math.abs(90 - angleNow) <= 10;
      if (touchTopBottom && nearHorizontal) score += 1000;
      if (touchLeftRight && nearVertical) score += 1000;
      if (touchTopBottom && !nearHorizontal) score -= 1000;
      if (touchLeftRight && !nearVertical) score -= 1000;
      score += Math.max(rotatedBounds.width, rotatedBounds.height);
    }
    try { clone.remove(); } catch (error) {}
    if (score > best.score) best = { angle: angle, score: score };
  }
  return best.angle;
}

function verifyLongestEdgeAgainstTemplate(parentLayer, documentRef) {
  var packArea = findPackAreaBounds(documentRef);
  var templateBounds = { left: packArea.left, top: packArea.top, right: packArea.right, bottom: packArea.bottom };
  var outline = findNamedPageItem(parentLayer, lazerOutlineName());
  if (outline === null) return 'Longest edge verify: false\nReason: missing ' + lazerOutlineName();
  var edges = getLongestOutlineEdges(outline);
  if (edges.length === 0) return 'Longest edge verify: false\nReason: no edge found';
  var outlineBounds = boundsOf(outline);
  var inside = boundsInsideTemplate(outlineBounds, templateBounds);
  var touch = countTemplateTouches(outlineBounds, templateBounds);
  var ok = touch >= 2 && inside;
  return [
    'Longest edge verify: ' + (ok ? 'true' : 'false'),
    'Inside Template: ' + inside,
    'Template touches: ' + touch,
    'Outline: ' + lazerOutlineName()
  ].join('\n');
}

function rotateItemsAroundUnion(items, angle) {
  if (Math.abs(angle) < 0.01) return;
  var union = unionVisibleBounds(items);
  if (union === null) return;
  var centerX = (union.left + union.right) / 2;
  var centerY = (union.top + union.bottom) / 2;
  for (var i = 0; i < items.length; i += 1) {
    try { items[i].rotate(angle, true, true, true, true, Transformation.CENTER); } catch (error) {}
  }
  var after = unionVisibleBounds(items);
  if (after === null) return;
  var afterCenterX = (after.left + after.right) / 2;
  var afterCenterY = (after.top + after.bottom) / 2;
  for (var j = 0; j < items.length; j += 1) {
    try { items[j].translate(centerX - afterCenterX, centerY - afterCenterY); } catch (error) {}
  }
}

function drawLongestEdgesDebug(documentRef, outline) {
  var debugLayer = ensureLayer(documentRef, longestEdgesLayerName());
  removeNamedPageItems(debugLayer, longestEdgesLayerName());
  var edges = getLongestOutlineEdges(outline);
  for (var i = 0; i < edges.length; i += 1) {
    try {
      var edge = edges[i];
      var line = debugLayer.pathItems.add();
      line.name = longestEdgesLayerName();
      line.stroked = true;
      line.strokeWidth = 1;
      line.strokeColor = red();
      line.filled = false;
      line.setEntirePath([[edge.a[0], edge.a[1]], [edge.b[0], edge.b[1]]]);
      line.closed = false;
      unlockAndShow(line);
    } catch (error) {}
  }
  return edges;
}

function packImagesOnSheet(parentLayer, documentRef) {
  var lazerOutlineBeforeGroup = findNamedPageItem(parentLayer, lazerOutlineName());
  var longestEdges = [];
  if (lazerOutlineBeforeGroup !== null) longestEdges = drawLongestEdgesDebug(documentRef, lazerOutlineBeforeGroup);

  var wholeImagesGroup = groupContainerItems(parentLayer, 'TEMP_PACK_GROUP_IMAGES', false);
  if (wholeImagesGroup === null) return;

  var borderLayer = null;
  try { borderLayer = ensureLayer(documentRef, 'BORDER'); } catch (error) { borderLayer = null; }
  var borderObstacles = borderLayer !== null ? collectVisibleBounds(borderLayer) : [];
  var paddedObstacles = [];
  for (var b = 0; b < borderObstacles.length; b += 1) {
    paddedObstacles.push(expandBounds(borderObstacles[b], PACK_GAP_POINT));
  }

  // Khong xoay tung lazer/front/back rieng le o day.
  // Tu buoc nay tro di chi xoay/di chuyen nguyen TEMP_PACK_GROUP_IMAGES.

  var packArea = findPackAreaBounds(documentRef);
  var usableLeft = packArea.left;
  var usableTop = packArea.top;
  var usableRight = packArea.right;
  var usableBottom = packArea.bottom;
  var lazerOutline = findNamedPageItem(parentLayer, lazerOutlineName());
  var templateBounds = { left: usableLeft, top: usableTop, right: usableRight, bottom: usableBottom };
  if (lazerOutline !== null) {
    var freeChoice = chooseFreeRotationForTemplate(lazerOutline, templateBounds);
    addReport([
      'Rotation choice',
      'Original longest edge angle: ' + Math.round(freeChoice.originalEdgeAngle * 100) / 100 + ' deg',
      'Rotate angle: ' + freeChoice.angle + ' deg'
    ].join('\n'));
    rotateItemsAroundUnion([wholeImagesGroup], freeChoice.angle);
  }
  lazerOutline = findNamedPageItem(parentLayer, lazerOutlineName());
  var anchorBounds = lazerOutline !== null ? boundsOf(lazerOutline) : boundsOf(wholeImagesGroup);
  var freePlacement = null;
  try { freePlacement = findFreePlacementInsideTemplate(anchorBounds, templateBounds, paddedObstacles); } catch (error) { freePlacement = null; }
  var delta = freePlacement !== null ? { dx: freePlacement.dx, dy: freePlacement.dy } : deltaToPlaceBoundsInsideTemplate(anchorBounds, templateBounds);
  try { wholeImagesGroup.translate(delta.dx, delta.dy); } catch (error) {}
  try { duplicatePackedGroupByQty(wholeImagesGroup, templateBounds, paddedObstacles); } catch (error) { addReport('QTY duplicate error'); }

  addReport([
    'PACK DONE',
    'Area: ' + packArea.source,
    'Start: top-left Margin',
    'BORDER obstacles: ' + paddedObstacles.length,
    'Mode: move whole TEMP_PACK_GROUP_IMAGES'
  ].join('\n'));

  // Keep TEMP_PACK_GROUP_IMAGES grouped after arranging. Ungroup later only when requested.
}


function duplicatePackedGroupByQty(groupItem, templateBounds, baseObstacles) {
  var qty = 1;
  try { if (typeof CODEX_ITEM_QTY !== 'undefined') qty = Math.max(1, Number(CODEX_ITEM_QTY)); } catch (error) { qty = 1; }
  if (qty <= 1 || groupItem === null) return 1;
  var obstacles = [];
  for (var i = 0; i < baseObstacles.length; i += 1) obstacles.push(baseObstacles[i]);
  var baseFootprint = null;
  try {
    var baseOutline = findNamedPageItem(groupItem, lazerOutlineName());
    if (baseOutline !== null) baseFootprint = expandBounds(boundsOf(baseOutline), PACK_GAP_POINT);
  } catch (error) {}
  if (baseFootprint === null) {
    try { baseFootprint = expandBounds(boundsOf(groupItem), PACK_GAP_POINT); } catch (error) { baseFootprint = null; }
  }
  if (baseFootprint !== null) obstacles.push(baseFootprint);
  var made = 1;
  for (var copyIndex = 2; copyIndex <= qty; copyIndex += 1) {
    var copy = null;
    try { copy = groupItem.duplicate(groupItem.parent, ElementPlacement.PLACEATEND); } catch (error) { copy = null; }
    if (copy === null) continue;
    try { copy.name = 'TEMP_PACK_GROUP_IMAGES'; } catch (error) {}
    var copyFootprint = null;
    try {
      var copyOutline = findNamedPageItem(copy, lazerOutlineName());
      if (copyOutline !== null) copyFootprint = boundsOf(copyOutline);
    } catch (error) {}
    if (copyFootprint === null) {
      try { copyFootprint = boundsOf(copy); } catch (error) { copyFootprint = null; }
    }
    var copyOutlineForRotate = null;
    try { copyOutlineForRotate = findNamedPageItem(copy, lazerOutlineName()); } catch (error) { copyOutlineForRotate = null; }
    if (copyOutlineForRotate !== null) {
      var copyRotate = chooseRotationByTemplateEdgeRule(copyOutlineForRotate, templateBounds);
      try { rotateItemsAroundUnion([copy], copyRotate); } catch (error) {}
      try { copyOutlineForRotate = findNamedPageItem(copy, lazerOutlineName()); } catch (error) { copyOutlineForRotate = null; }
      if (copyOutlineForRotate !== null) copyFootprint = boundsOf(copyOutlineForRotate);
    }
    var placement = null;
    try { placement = findFreePlacementInsideTemplate(copyFootprint, templateBounds, obstacles); } catch (error) { placement = null; }
    if (placement === null) {
      try { copy.remove(); } catch (error) {}
      addReport('QTY copy ' + copyIndex + ': false - khong du cho trong Template');
      continue;
    }
    try { copy.translate(placement.dx, placement.dy); } catch (error) {}
    try {
      var movedOutline = findNamedPageItem(copy, lazerOutlineName());
      if (movedOutline !== null) obstacles.push(expandBounds(boundsOf(movedOutline), PACK_GAP_POINT));
      else obstacles.push(expandBounds(boundsOf(copy), PACK_GAP_POINT));
    } catch (error) {}
    made += 1;
  }
  addReport('QTY requested: ' + qty);
  addReport('QTY created: ' + made);
  return made;
}
function groupCaseLayer(documentRef, caseLayer) {
  var items = [];
  for (var i = 0; i < caseLayer.pageItems.length; i += 1) {
    try {
      unlockAndShow(caseLayer.pageItems[i]);
      items.push(caseLayer.pageItems[i]);
    } catch (error) {}
  }
  if (items.length <= 1) return items.length === 1 ? items[0] : null;
  documentRef.selection = null;
  for (var j = 0; j < items.length; j += 1) {
    try { items[j].selected = true; } catch (error) {}
  }
  try { app.executeMenuCommand('group'); } catch (error) {}
  var group = null;
  if (documentRef.selection !== null && documentRef.selection.length > 0) {
    try { group = documentRef.selection[0]; } catch (error) {}
  }
  if (group !== null) {
    try { group.name = 'TEMP_ALIGN_GROUP_' + caseLayer.name; } catch (error) {}
  }
  return group;
}

function selectAllImagesItems(documentRef, parentLayer) {
  var items = [];
  documentRef.selection = null;
  for (var i = 0; i < parentLayer.layers.length; i += 1) {
    var caseLayer = parentLayer.layers[i];
    if (isCaseLayerName(caseLayer.name, 'lazer')) {
      var grouped = groupCaseLayer(documentRef, caseLayer);
      if (grouped !== null) items.push(grouped);
      continue;
    }
    for (var j = 0; j < caseLayer.pageItems.length; j += 1) {
      try {
        unlockAndShow(caseLayer.pageItems[j]);
        items.push(caseLayer.pageItems[j]);
      } catch (error) {}
    }
  }
  documentRef.selection = null;
  for (var k = 0; k < items.length; k += 1) {
    try { items[k].selected = true; } catch (error) {}
  }
  return items;
}

function ungroupTempAlignGroup(container, groupName) {
  for (var i = container.groupItems.length - 1; i >= 0; i -= 1) {
    try {
      var group = container.groupItems[i];
      if (group.name !== groupName) {
        ungroupTempAlignGroup(group, groupName);
        continue;
      }
      unlockAndShow(group);
      while (group.pageItems.length > 0) {
        try { group.pageItems[0].move(container, ElementPlacement.PLACEATEND); } catch (error) { break; }
      }
      group.remove();
    } catch (error) {}
  }
}

function ungroupLazer(documentRef, parentLayer) {
  for (var i = 0; i < parentLayer.layers.length; i += 1) {
    var caseLayer = parentLayer.layers[i];
    if (!isCaseLayerName(caseLayer.name, 'lazer')) continue;
    ungroupTempAlignGroup(caseLayer, 'TEMP_ALIGN_GROUP_lazer');
  }
  documentRef.selection = null;
}

function alignLazerBeforeScale(documentRef, parentLayer) {
  var lazerClip = findNamedPageItem(parentLayer, caseLayerName('lazer'));
  var lazerDebug = findNamedPageItem(parentLayer, 'DEBUG_BLACK_PIXEL_BOUNDS_lazer');
  if (lazerClip === null || lazerDebug === null) return;
  unlockAndShow(parentLayer);
  unlockAndShow(lazerClip);
  unlockAndShow(lazerDebug);
  documentRef.selection = null;
  try { lazerClip.selected = true; } catch (error) {}
  try { lazerDebug.selected = true; } catch (error) {}
  try { app.executeMenuCommand('group'); } catch (error) {}
  var tempGroup = null;
  if (documentRef.selection !== null && documentRef.selection.length > 0) {
    try { tempGroup = documentRef.selection[0]; } catch (error) {}
  }
  if (tempGroup === null) return;
  try { tempGroup.name = 'TEMP_ALIGN_GROUP_lazer'; } catch (error) {}
  unlockAndShow(tempGroup);
  documentRef.selection = null;
  var selectableItems = [];
  collectSelectablePageItems(parentLayer, selectableItems, false);
  for (var i = 0; i < selectableItems.length; i += 1) {
    try { selectableItems[i].selected = true; } catch (error) {}
  }
  try { app.executeMenuCommand('Horizontal Align Center'); } catch (error) {}
  try { app.redraw(); } catch (error) {}
  documentRef.selection = null;
  selectableItems = [];
  collectSelectablePageItems(parentLayer, selectableItems, false);
  for (var j = 0; j < selectableItems.length; j += 1) {
    try { selectableItems[j].selected = true; } catch (error) {}
  }
  try { app.executeMenuCommand('Vertical Align Center'); } catch (error) {}
  try { app.redraw(); } catch (error) {}
  ungroupTempAlignGroup(parentLayer, 'TEMP_ALIGN_GROUP_lazer');
}

function green() {
  var color = new RGBColor(); color.red = 0; color.green = 180; color.blue = 80; return color;
}

function removeNamedPageItems(container, itemName) {
  for (var i = container.pageItems.length - 1; i >= 0; i -= 1) {
    try {
      var item = container.pageItems[i];
      if (item.typename === 'GroupItem') removeNamedPageItems(item, itemName);
      if (item.name === itemName) {
        unlockAndShow(item);
        item.remove();
      }
    } catch (error) {}
  }
}

function removeDebugLazer(parentLayer) {
  removeDebugByLabel(parentLayer, 'lazer');
}

function moveNamedItemToLayer(sourceContainer, itemName, targetLayer) {
  var item = findNamedPageItem(sourceContainer, itemName);
  if (item === null) return false;
  try {
    unlockAndShow(targetLayer);
    unlockAndShow(item);
    item.move(targetLayer, ElementPlacement.PLACEATBEGINNING);
    return true;
  } catch (error) {}
  return false;
}

function moveAllNamedItemsToLayer(sourceContainer, itemName, targetLayer) {
  var count = 0;
  while (true) {
    var moved = moveNamedItemToLayer(sourceContainer, itemName, targetLayer);
    if (!moved) break;
    count += 1;
    if (count > 500) break;
  }
  return count;
}

function finalMoveItemsToOutputLayers(documentRef, parentLayer) {
  removeDocumentLayer(documentRef, longestEdgesLayerName());
  try { ungroupCaseItems(parentLayer, 'TEMP_PACK_GROUP_IMAGES'); } catch (error) {}

  var borderLayer = ensureLayer(documentRef, 'BORDER');
  var backLayer = ensureLayer(documentRef, 'BACK');
  var frontLayer = ensureLayer(documentRef, 'FRONT');
  var lazerLayer = ensureLayer(documentRef, 'LAZER');

  var movedBorder = moveAllNamedItemsToLayer(parentLayer, lazerOutlineName(), borderLayer);
  var movedBack = moveAllNamedItemsToLayer(parentLayer, caseLayerName('back'), backLayer);
  var movedFront = moveAllNamedItemsToLayer(parentLayer, caseLayerName('front'), frontLayer);
  var movedLazer = moveAllNamedItemsToLayer(parentLayer, caseLayerName('lazer'), lazerLayer);
  addReport('Moved BORDER: ' + movedBorder);
  addReport('Moved BACK: ' + movedBack);
  addReport('Moved FRONT: ' + movedFront);
  addReport('Moved LAZER: ' + movedLazer);
}
function cross(o, a, b) {
  return ((a[0] - o[0]) * (b[1] - o[1])) - ((a[1] - o[1]) * (b[0] - o[0]));
}

function sortPointsForHull(points) {
  points.sort(function(a, b) {
    if (a[0] !== b[0]) return a[0] - b[0];
    return a[1] - b[1];
  });
  var unique = [];
  for (var i = 0; i < points.length; i += 1) {
    if (i === 0 || points[i][0] !== points[i - 1][0] || points[i][1] !== points[i - 1][1]) unique.push(points[i]);
  }
  return unique;
}

function convexHull(points) {
  var sorted = sortPointsForHull(points);
  if (sorted.length <= 3) return sorted;
  var lower = [];
  for (var i = 0; i < sorted.length; i += 1) {
    while (lower.length >= 2 && cross(lower[lower.length - 2], lower[lower.length - 1], sorted[i]) <= 0) lower.pop();
    lower.push(sorted[i]);
  }
  var upper = [];
  for (var j = sorted.length - 1; j >= 0; j -= 1) {
    while (upper.length >= 2 && cross(upper[upper.length - 2], upper[upper.length - 1], sorted[j]) <= 0) upper.pop();
    upper.push(sorted[j]);
  }
  lower.pop();
  upper.pop();
  return lower.concat(upper);
}
function drawLazerOutlineAfterScale(parentLayer) {
  if (!CODEX_COLORED_METRICS || !CODEX_COLORED_METRICS.components || CODEX_COLORED_METRICS.components.length === 0) return null;
  removeNamedPageItems(parentLayer, lazerOutlineName());
  var component = CODEX_COLORED_METRICS.components[0];
  if (!component || !component.sampledOutline || component.sampledOutline.length === 0) return null;
  var debugItem = findDebugBounds(parentLayer, 'lazer');
  if (debugItem === null) return null;
  var db = boundsOf(debugItem);
  var scaleX = db.width / CODEX_COLORED_METRICS.components[0].widthPx;
  var scaleY = db.height / CODEX_COLORED_METRICS.components[0].heightPx;
  var pathPoints = [];
  for (var p = 0; p < component.sampledOutline.length; p += 1) {
    var point = component.sampledOutline[p];
    var x = db.left + (point.x - component.minX) * scaleX;
    var y = db.top - (point.y - component.minY) * scaleY;
    pathPoints.push([x, y]);
  }
  if (pathPoints.length < 3) return null;
  pathPoints = convexHull(pathPoints);
  if (pathPoints.length < 3) return null;
  var outline = parentLayer.pathItems.add();
  outline.name = lazerOutlineName();
  outline.stroked = true;
  outline.strokeWidth = 0.5;
  outline.strokeColor = green();
  outline.filled = false;
  if (pathPoints.length >= 2) {
    var flatPoints = [];
    for (var r = 0; r < pathPoints.length; r += 1) {
      flatPoints.push(pathPoints[r][0]);
      flatPoints.push(pathPoints[r][1]);
    }
    try {
      outline.setEntirePath(pathPoints);
      outline.closed = true;
    } catch (error) {
      outline.remove();
      return null;
    }
  }
  unlockAndShow(outline);
  try { outline.zOrder(ZOrderMethod.BRINGTOFRONT); } catch (error) {}
  return outline;
}
function runCase(documentRef, parentLayer, label, verticalMode, offsetIndex, preferredIndex, useUnion) {
  var caseLayer = createCaseLayer(parentLayer, label);
  var mask = createMask(caseLayer, documentRef, label, offsetIndex);
  var raster = embedImage(caseLayer, documentRef, CODEX_IMAGE_PATH, label, offsetIndex);
  if (raster === null) throw new Error('Khong tim thay IMAGE_' + label);
  alignImageToMask(raster, mask, verticalMode);
  var group = makeClip(caseLayer, raster, mask, label);
  var clippedRaster = findRasterInGroup(group);
  if (clippedRaster === null) throw new Error('CLIP_IMAGE_30_48CM_' + label + ' thieu IMAGE_' + label);
  var boundsList = allColoredBounds(clippedRaster, CODEX_COLORED_METRICS);
  var picked = pickBounds(mask, boundsList, preferredIndex, useUnion === true);
  drawDebug(caseLayer, picked, label);
  app.redraw();
  addReport(report(label, mask, picked));
}

function existsNamedItem(container, itemName) {
  for (var i = 0; i < container.pageItems.length; i += 1) {
    try {
      var item = container.pageItems[i];
      if (item.name === itemName) return true;
      if (item.typename === 'GroupItem' && existsNamedItem(item, itemName)) return true;
    } catch (error) {}
  }
  return false;
}

function existsSublayer(parentLayer, layerName) {
  for (var i = 0; i < parentLayer.layers.length; i += 1) {
    try { if (parentLayer.layers[i].name === layerName) return true; } catch (error) {}
  }
  return false;
}

function auditImagesLayer(parentLayer) {
  var messages = [];
  var documentRef = app.activeDocument;
  var packArea = findPackAreaBounds(app.activeDocument);
  for (var i = 0; i < CODEX_REPORTS.length; i += 1) {
    messages.push(CODEX_REPORTS[i]);
    messages.push('---');
  }
  messages.push('AUDIT Images');
  messages.push('pack area: ' + packArea.source);
  messages.push('pack top-left: ' + ptToCm(packArea.left) + 'cm, ' + ptToCm(packArea.top) + 'cm');
  messages.push('LAZER output: ' + existsNamedItem(ensureLayer(documentRef, 'LAZER'), caseLayerName('lazer')));
  messages.push('FRONT output: ' + existsNamedItem(ensureLayer(documentRef, 'FRONT'), caseLayerName('front')));
  messages.push('BACK output: ' + existsNamedItem(ensureLayer(documentRef, 'BACK'), caseLayerName('back')));
  messages.push('BORDER output: ' + existsNamedItem(ensureLayer(documentRef, 'BORDER'), lazerOutlineName()));
  messages.push('debug lazer removed: ' + !existsNamedItem(parentLayer, 'DEBUG_BLACK_PIXEL_BOUNDS_lazer'));
  messages.push('debug front removed: ' + !existsNamedItem(parentLayer, 'DEBUG_BLACK_PIXEL_BOUNDS_front'));
  messages.push('debug back removed: ' + !existsNamedItem(parentLayer, 'DEBUG_BLACK_PIXEL_BOUNDS_back'));
  messages.push('temp scale absent: ' + !existsNamedItem(parentLayer, 'TEMP_SCALE_GROUP_IMAGES'));
  messages.push('temp align absent: ' + !existsNamedItem(parentLayer, 'TEMP_ALIGN_GROUP_lazer'));
  messages.push('temp pack removed: ' + !existsNamedItem(parentLayer, 'TEMP_PACK_GROUP_IMAGES'));
  messages.push('longest edge layer removed: ' + !existsSublayer(app.activeDocument, longestEdgesLayerName()));
  messages.push('---');
  messages.push(verifyLongestEdgeAgainstTemplate(parentLayer, app.activeDocument));
  alert(messages.join('\n'));
}
function run() {
  var documentRef = getOrOpenTemplate(CODEX_TEMPLATE_PATH);
  var parentLayer = ensureLayer(documentRef, 'Images');
  runCase(documentRef, parentLayer, 'lazer', 'top', 0, 0);
  runCase(documentRef, parentLayer, 'front', 'center', 1, 1, true);
  if (CODEX_SIDE_COUNT >= 2) runCase(documentRef, parentLayer, 'back', 'bottom', 2, 2, true);
  alignLazerBeforeScale(documentRef, parentLayer);
  scaleImagesByLazerSize(documentRef, parentLayer);
  removeDebugByLabel(parentLayer, 'front');
  removeDebugByLabel(parentLayer, 'back');
  drawLazerOutlineAfterScale(parentLayer);
  packImagesOnSheet(parentLayer, documentRef);
  removeDebugLazer(parentLayer);
  finalMoveItemsToOutputLayers(documentRef, parentLayer);
  auditImagesLayer(parentLayer);
  app.redraw();
}

run();
