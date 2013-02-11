/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */

/**
 * Container for location components, go to parent, refresh.
 */
Class.create("Breadcrumb", {
	__implements : ["IAjxpWidget"],
    currentPath : "",
	/**
	 * Constructor
	 * @param oElement HTMLElement
	 * @param options Object
	 */
	initialize : function(oElement, options){
		this.element = oElement;
		this.element.ajxpPaneObject = this;
        this.options = options || {};
        this.element.update('Files');
        document.observe("ajaxplorer:context_changed", function(event){
            var newNode = event.memo;
            if(Object.isString(newNode)){
                newNode = new AjxpNode(newNode);
            }
            var newPath = newNode.getPath();
            if(getBaseName(newPath) != newNode.getLabel()){
                this.currentLabel = getRepName(newPath) + '/' + newNode.getLabel();
            }
            this.currentPath = newPath;
            newPath = "<span class='icon-home'></span>" + newPath.split("/").join("<span class='icon-chevron-right'></span>")
            this.element.update("<div class='inner_bread'>" + newPath + "</div>");

        }.bind(this) );
	},

	/**
	 * Resize widget
	 */
	resize : function(){
		if(this.options.flexTo){
			var parentWidth = $(this.options.flexTo).getWidth();
			var siblingWidth = 0;
			this.element.siblings().each(function(s){
				if(s.ajxpPaneObject && s.ajxpPaneObject.getActualWidth){
					siblingWidth+=s.ajxpPaneObject.getActualWidth();
				}else{
					siblingWidth+=s.getWidth();
				}
			});
            var buttonsWidth = 0;
            this.element.select("div.inlineBarButton,div.inlineBarButtonLeft,div.inlineBarButtonRight").each(function(el){
                buttonsWidth += el.getWidth();
            });
			var newWidth = (parentWidth-siblingWidth-30);
			if(newWidth < 5){
				this.element.hide();
			}else{
				this.element.show();
				this.element.setStyle({width:newWidth + 'px'});
			}
		}
	},
	
	/**
	 * Implementation of the IAjxpWidget methods
	 */	
	getDomNode : function(){
		return this.element;
	},
	
	/**
	 * Implementation of the IAjxpWidget methods
	 */	
	destroy : function(){
		this.element = null;
	},

	/**
	 * Do nothing
	 * @param show Boolean
	 */
	showElement : function(show){

    }
});